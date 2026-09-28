<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Actions\AccrueInvoicePenalties;
use App\Modules\Billing\Actions\IssueCreditNote;
use App\Modules\Billing\Actions\VoidInvoice;
use App\Modules\Billing\Actions\WaivePenalties;
use App\Modules\Finance\Actions\DeductDeposit;
use App\Modules\Finance\Actions\RecordExpense;
use App\Modules\Finance\Actions\RefundDeposit;
use App\Modules\Finance\Actions\TransferDeposit;
use App\Modules\Finance\Actions\VoidExpense;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Journal\JournalLineDraft;
use App\Modules\Finance\Journal\JournalPoster;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\FiscalPeriod;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\States\FiscalPeriod\Closed;
use App\Modules\Lease\Actions\RenewContract;
use App\Modules\Payment\Actions\ConfirmCashHandover;
use App\Modules\Payment\Actions\RecordCashHandover;
use App\Modules\Payment\Actions\ReversePayment;
use App\Modules\Property\Actions\AssignStaffToProperty;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\LedgerScenario;
use Tests\Support\PaymentScenario;

/*
 * Automatic journals for every event in PRD §8.14. Account codes come from
 * the built-in chart: 1-1000 Kas, 1-1100 kas di tangan staf, 1-1200 bank,
 * 1-1300 piutang, 2-1000 utang deposit, 2-1100 saldo kredit, 4-1000 sewa,
 * 4-1200 denda, 4-1900 pendapatan lain-lain, 5-1000 listrik dan air,
 * 5-1900 beban lain-lain.
 */
beforeEach(function () {
    $this->travelTo('2026-09-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->contract = LeaseScenario::active();
    [$this->invoice] = BillingScenario::issueDue($this->contract);
    $this->bank = PaymentScenario::bankAccount();
});

afterEach(function () {
    expect(LedgerScenario::unbalancedEntries())->toBe([]);
});

function lastJournal(JournalEvent $event): JournalEntry
{
    return JournalEntry::query()->where('event', $event->value)->orderByDesc('id')->firstOrFail();
}

it('books rent as owed and earned when an invoice is issued, leaving the deposit out', function () {
    $entry = lastJournal(JournalEvent::InvoiceIssued);

    expect(LedgerScenario::lines($entry))->toBe([['1-1300', 1_200_000], ['4-1000', -1_200_000]])
        ->and($entry->number)->toBe('JU/2026/09/00001')
        ->and($entry->entry_date->toDateString())->toBe('2026-09-15')
        ->and($entry->source_id)->toBe($this->invoice->id)
        ->and($entry->lines()->where('account_id', Account::system(AccountSubtype::Receivable)->id)->value('contract_id'))->toBe($this->contract->id)
        ->and(LedgerScenario::balance(AccountSubtype::DepositLiability))->toBe(0);
});

it('books a payment to the bank, settling rent and receiving deposit, with the rest as credit', function () {
    PaymentScenario::transfer($this->contract, 2_500_000);

    expect(LedgerScenario::lines(lastJournal(JournalEvent::PaymentVerified)))->toBe([
        [$this->bank->ledgerAccount()->firstOrFail()->code, 2_500_000],
        ['2-1000', -1_200_000],
        ['1-1300', -1_200_000],
        ['2-1100', -100_000],
    ])
        ->and(LedgerScenario::balance(AccountSubtype::Receivable, $this->contract->id))->toBe(0)
        ->and(LedgerScenario::balance(AccountSubtype::CreditLiability, $this->contract->id))->toBe(100_000);
});

it('moves credit onto the next invoice', function () {
    PaymentScenario::transfer($this->contract, 2_500_000);
    $this->travelTo('2026-10-15 03:00:00');
    BillingScenario::issueDue($this->contract);

    expect(LedgerScenario::lines(lastJournal(JournalEvent::CreditApplied)))->toBe([['2-1100', 100_000], ['1-1300', -100_000]])
        ->and(LedgerScenario::balance(AccountSubtype::CreditLiability))->toBe(0)
        ->and(LedgerScenario::balance(AccountSubtype::Receivable))->toBe(1_100_000);
});

it('books penalties and reverses them when waived', function () {
    BillingScenario::settings($this->contract->property()->firstOrFail(), ['penalty_type' => 'flat', 'penalty_amount' => 50_000]);
    $this->travelTo('2026-09-25 03:00:00');
    app(AccrueInvoicePenalties::class)->handle($this->invoice->refresh());

    expect(LedgerScenario::lines(lastJournal(JournalEvent::PenaltyAccrued)))->toBe([['1-1300', 50_000], ['4-1200', -50_000]]);

    app(WaivePenalties::class)->handle($this->invoice, ['reason' => 'Transfer tertahan di bank']);

    $reversal = lastJournal(JournalEvent::PenaltyWaived);

    expect(LedgerScenario::lines($reversal))->toBe([['1-1300', -50_000], ['4-1200', 50_000]])
        ->and($reversal->reversal_of_id)->toBe(lastJournal(JournalEvent::PenaltyAccrued)->id)
        ->and(LedgerScenario::balance(AccountSubtype::PenaltyRevenue))->toBe(0);
});

it('takes income back with a credit note, and not for the deposit', function () {
    app(IssueCreditNote::class)->handle($this->invoice, ['allocation_category' => 'rent', 'amount' => 200_000, 'reason' => 'Air mati seminggu']);
    app(IssueCreditNote::class)->handle($this->invoice, ['allocation_category' => 'deposit', 'amount' => 200_000, 'reason' => 'Deposit dikurangi']);

    expect(LedgerScenario::lines(lastJournal(JournalEvent::CreditNoteIssued)))->toBe([['4-1000', 200_000], ['1-1300', -200_000]])
        ->and(JournalEntry::query()->where('event', JournalEvent::CreditNoteIssued->value)->count())->toBe(1)
        ->and(LedgerScenario::balance(AccountSubtype::Receivable))->toBe(1_000_000);
});

it('reverses the invoice and its penalties when voided', function () {
    BillingScenario::settings($this->contract->property()->firstOrFail(), ['penalty_type' => 'flat', 'penalty_amount' => 50_000]);
    $this->travelTo('2026-09-25 03:00:00');
    app(AccrueInvoicePenalties::class)->handle($this->invoice->refresh());

    app(VoidInvoice::class)->handle($this->invoice, ['reason' => 'Salah harga sewa']);

    expect(JournalEntry::query()->where('event', JournalEvent::InvoiceVoided->value)->count())->toBe(2)
        ->and(LedgerScenario::balance(AccountSubtype::Receivable))->toBe(0)
        ->and(LedgerScenario::balance(AccountSubtype::RentRevenue))->toBe(0)
        ->and(LedgerScenario::balance(AccountSubtype::PenaltyRevenue))->toBe(0);
});

it('undoes a reversed payment, including credit already used', function () {
    PaymentScenario::transfer($this->contract, 2_400_000);
    $extra = PaymentScenario::transfer($this->contract, 500_000);
    $this->travelTo('2026-10-15 03:00:00');
    BillingScenario::issueDue($this->contract);

    app(ReversePayment::class)->handle($extra, ['reason' => 'Tercatat dua kali']);

    expect(LedgerScenario::lines(lastJournal(JournalEvent::PaymentReversed)))->toBe([
        [$this->bank->ledgerAccount()->firstOrFail()->code, -500_000],
        ['2-1100', 500_000],
    ])
        ->and(LedgerScenario::account($this->bank->ledger_account_id))->toBe(2_400_000)
        ->and(LedgerScenario::balance(AccountSubtype::CreditLiability))->toBe(0)
        ->and(LedgerScenario::balance(AccountSubtype::Receivable))->toBe(1_200_000);
});

it('releases paid money to credit when a paid invoice gets a credit note', function () {
    PaymentScenario::transfer($this->contract, 2_400_000);

    app(IssueCreditNote::class)->handle($this->invoice, ['allocation_category' => 'rent', 'amount' => 200_000, 'reason' => 'Air mati seminggu']);

    expect(LedgerScenario::lines(lastJournal(JournalEvent::AllocationReleased)))->toBe([['1-1300', 200_000], ['2-1100', -200_000]])
        ->and(LedgerScenario::balance(AccountSubtype::Receivable))->toBe(0)
        ->and(LedgerScenario::balance(AccountSubtype::CreditLiability))->toBe(200_000);
});

it('books deposit deductions, refunds, transfers, and invoices paid from deposit', function () {
    PaymentScenario::transfer($this->contract, 2_400_000);

    app(DeductDeposit::class)->handle($this->contract, ['amount' => 100_000, 'reason' => 'Kunci hilang']);
    expect(LedgerScenario::lines(lastJournal(JournalEvent::DepositDeducted)))->toBe([['2-1000', 100_000], ['4-1900', -100_000]]);

    app(RefundDeposit::class)->handle($this->contract, ['amount' => 300_000, 'account_id' => $this->bank->ledger_account_id]);
    expect(LedgerScenario::lines(lastJournal(JournalEvent::DepositRefunded)))->toBe([
        ['2-1000', 300_000],
        [$this->bank->ledgerAccount()->firstOrFail()->code, -300_000],
    ]);

    $renewal = app(RenewContract::class)->handle($this->contract, ['start_date' => '2027-09-20', 'rent_amount' => 1_300_000]);
    app(TransferDeposit::class)->handle($this->contract, ['to_contract_id' => $renewal->id, 'amount' => 800_000]);

    expect(LedgerScenario::balance(AccountSubtype::DepositLiability, $this->contract->id))->toBe(0)
        ->and(LedgerScenario::balance(AccountSubtype::DepositLiability, $renewal->id))->toBe(800_000)
        ->and(LedgerScenario::balance(AccountSubtype::DepositLiability))->toBe(800_000);
});

it('books an expense and reverses it when voided', function () {
    $kas = Account::system(AccountSubtype::Cash);
    $electricity = Account::system(AccountSubtype::UtilityExpense);

    $expense = app(RecordExpense::class)->handle($this->contract->property()->firstOrFail(), [
        'expense_account_id' => $electricity->id,
        'paid_from_account_id' => $kas->id,
        'amount' => 350_000,
        'spent_on' => '2026-09-14',
        'description' => 'Token listrik lorong',
    ]);

    $entry = lastJournal(JournalEvent::ExpenseRecorded);

    expect(LedgerScenario::lines($entry))->toBe([['5-1000', 350_000], ['1-1000', -350_000]])
        ->and($entry->entry_date->toDateString())->toBe('2026-09-14');

    app(VoidExpense::class)->handle($expense, ['reason' => 'Dicatat dua kali']);

    expect(LedgerScenario::account($kas))->toBe(0)
        ->and(LedgerScenario::account($electricity))->toBe(0);
});

it('books a staff handover with a shortfall as an expense', function () {
    $caretaker = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->contract->property()->firstOrFail(), $caretaker);
    loginAs($caretaker);
    PaymentScenario::cash($this->contract, 2_400_000, $caretaker);
    $handover = app(RecordCashHandover::class)->handle($this->contract->property()->firstOrFail(), [
        'actual_amount' => 2_350_000,
        'destination_account_id' => Account::system(AccountSubtype::Cash)->id,
        'handed_over_at' => now()->toDateTimeString(),
    ]);
    loginAs($this->owner);

    app(ConfirmCashHandover::class)->handle($handover, ['difference_note' => 'Dipakai beli lampu lorong']);

    $staffCash = Account::query()->where('user_id', $caretaker->id)->sole();

    expect(LedgerScenario::lines(lastJournal(JournalEvent::CashHandedOver)))->toBe([
        ['1-1000', 2_350_000],
        [$staffCash->code, -2_400_000],
        ['5-1900', 50_000],
    ])
        ->and(LedgerScenario::account($staffCash))->toBe(0);
});

it('refuses an unbalanced journal', function () {
    $kas = Account::system(AccountSubtype::Cash)->id;

    DB::transaction(fn () => app(JournalPoster::class)->post(
        JournalEvent::ExpenseRecorded, now(), 'Coba', null, null, [JournalLineDraft::debit($kas, 1)],
    ));
})->throws(LogicException::class, 'tidak seimbang');

it('refuses journals dated in a closed period', function () {
    $period = FiscalPeriod::for(now());
    $period->status->transitionTo(Closed::class);

    PaymentScenario::transfer($this->contract, 2_400_000);
})->throws(ValidationException::class, 'sudah ditutup');

it('never edits or deletes a journal', function () {
    $entry = lastJournal(JournalEvent::InvoiceIssued);

    expect(fn () => $entry->update(['description' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and(fn () => $entry->lines()->firstOrFail()->delete())->toThrow(LogicException::class);
});
