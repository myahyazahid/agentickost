<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Finance\Actions\DeductDeposit;
use App\Modules\Finance\Actions\RecordExpense;
use App\Modules\Finance\Actions\RefundDeposit;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Finance\Support\LedgerBalances;
use App\Modules\Payment\Actions\ConfirmCashHandover;
use App\Modules\Payment\Actions\RecordCashHandover;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Support\CreditLedger;
use App\Modules\Payment\Support\StaffCash;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Models\Room;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\LedgerScenario;
use Tests\Support\PaymentScenario;

/**
 * Roadmap M1.5 done criterion: a full cycle of invoices, payments, deposit,
 * and expenses leaves a balanced ledger whose balances match the cash, the
 * deposits held, the credit, and what residents still owe.
 */
it('keeps the ledger balanced and in line with cash, deposits, credit, and arrears', function () {
    $this->travelTo('2026-09-15 03:00:00');
    $owner = loginAs(staff(Role::Owner));
    $roomA = LeaseScenario::room();
    $property = $roomA->property()->firstOrFail();
    $caretaker = staff(Role::Caretaker, $owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($property, $caretaker);
    $bank = PaymentScenario::bankAccount();
    $kas = Account::system(AccountSubtype::Cash);

    $a = LeaseScenario::active($roomA);
    $roomB = Room::factory()->forType($roomA->roomType()->firstOrFail())->create(['capacity' => 1]);
    $b = LeaseScenario::active($roomB, ['deposit_amount' => 500_000, 'start_date' => '2026-09-18']);
    BillingScenario::issueDue($a);
    BillingScenario::issueDue($b);

    PaymentScenario::transfer($a, 2_500_000);
    loginAs($caretaker);
    PaymentScenario::cash($b, 1_000_000, $caretaker);
    app(RecordExpense::class)->handle($property, [
        'expense_account_id' => Account::system(AccountSubtype::MaintenanceExpense)->id,
        'paid_from_account_id' => Account::query()->where('user_id', $caretaker->id)->value('id'),
        'amount' => 75_000,
        'spent_on' => '2026-09-15',
        'description' => 'Lampu lorong',
    ]);
    $handover = app(RecordCashHandover::class)->handle($property, [
        'actual_amount' => 900_000,
        'destination_account_id' => $kas->id,
        'handed_over_at' => now()->toDateTimeString(),
    ]);
    loginAs($owner);
    app(ConfirmCashHandover::class)->handle($handover, ['difference_note' => 'Kurang Rp25.000, diganti bulan depan']);

    app(DeductDeposit::class)->handle($a, ['amount' => 150_000, 'reason' => 'Kunci hilang']);
    app(RecordExpense::class)->handle($property, [
        'expense_account_id' => Account::system(AccountSubtype::UtilityExpense)->id,
        'paid_from_account_id' => $bank->ledger_account_id,
        'amount' => 400_000,
        'spent_on' => '2026-09-15',
        'description' => 'Tagihan PDAM September',
    ]);

    $this->travelTo('2026-10-15 03:00:00');
    BillingScenario::issueDue($a);
    BillingScenario::issueDue($b);
    PaymentScenario::cash($b, 700_000, $owner);
    app(RefundDeposit::class)->handle($a, ['amount' => 50_000, 'account_id' => $kas->id]);

    ['debit' => $debit, 'credit' => $credit] = LedgerBalances::trialTotals();

    expect(LedgerScenario::unbalancedEntries())->toBe([])
        ->and($debit)->toBe($credit)
        // Cash: 2.5 jt transfer, less 400 rb PDAM; 900 rb handed over and 700 rb from the owner, less a 50 rb refund.
        ->and(LedgerScenario::account($bank->ledger_account_id))->toBe(2_100_000)
        ->and(LedgerScenario::account($kas))->toBe(1_550_000)
        ->and(LedgerScenario::account(Account::query()->where('user_id', $caretaker->id)->sole()))
        ->toBe(StaffCash::balance($caretaker->id, $property->id))
        ->and(StaffCash::balance($caretaker->id, $property->id))->toBe(0);

    foreach ([$a, $b] as $contract) {
        $owed = (int) Invoice::query()
            ->where('contract_id', $contract->id)
            ->whereIn('status', InvoiceState::openValues())
            ->sum('balance_amount');

        expect(LedgerScenario::balance(AccountSubtype::DepositLiability, $contract->id))->toBe(DepositLedger::balance($contract->id))
            ->and(LedgerScenario::balance(AccountSubtype::CreditLiability, $contract->id))->toBe(CreditLedger::balance($contract->id))
            ->and(LedgerScenario::balance(AccountSubtype::Receivable, $contract->id) + unpaidDeposit($contract->id))->toBe($owed);
    }

    expect(DepositLedger::balance($a->id))->toBe(1_000_000)
        ->and(DepositLedger::balance($b->id))->toBe(500_000);
});

/**
 * Deposit billed but not yet paid: owed on the invoice, but not booked until
 * it arrives (PRD §8.14).
 */
function unpaidDeposit(string $contractId): int
{
    $billed = (int) Invoice::query()
        ->where('contract_id', $contractId)
        ->whereIn('status', InvoiceState::openValues())
        ->withSum(['items as deposit_billed' => fn ($query) => $query->where('allocation_category', 'deposit')], 'amount')
        ->get()
        ->sum('deposit_billed');

    $paid = (int) PaymentAllocation::query()
        ->active()
        ->where('allocation_category', 'deposit')
        ->whereIn('invoice_id', Invoice::query()->where('contract_id', $contractId)->whereIn('status', InvoiceState::openValues())->select('id'))
        ->sum('amount');

    return $billed - $paid;
}
