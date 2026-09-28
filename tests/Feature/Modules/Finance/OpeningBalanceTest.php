<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Actions\AccrueInvoicePenalties;
use App\Modules\Billing\Actions\VoidInvoice;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Finance\Actions\DeleteOpeningBalance;
use App\Modules\Finance\Actions\PostOpeningBalance;
use App\Modules\Finance\Actions\SaveOpeningBalance;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\OpeningBalance;
use App\Modules\Finance\States\OpeningBalance\Posted;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Payment\Support\CreditLedger;
use App\Modules\Property\Enums\PenaltyType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\LedgerScenario;
use Tests\Support\PaymentScenario;

/*
 * Opening balances (FR-ONB-04, FR-ONB-05). Account codes: 1-1000 Kas,
 * 1-1200-01 the first bank account, 1-1300 piutang, 2-1000 utang deposit,
 * 2-1100 saldo kredit, 3-1000 ekuitas saldo awal.
 */
beforeEach(function () {
    $this->travelTo('2026-10-05 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->first = LeaseScenario::imported(billingStartsOn: '2026-10-01');
    $this->second = LeaseScenario::imported(billingStartsOn: '2026-10-15');
    $this->bank = PaymentScenario::bankAccount();
});

afterEach(function () {
    expect(LedgerScenario::unbalancedEntries())->toBe([]);
});

/**
 * @param  list<array<string, mixed>>  $lines
 */
function openingBalance(array $lines, string $cutoff = '2026-09-30'): OpeningBalance
{
    return app(SaveOpeningBalance::class)->handle(['cutoff_date' => $cutoff, 'lines' => $lines]);
}

function standardOpening(): OpeningBalance
{
    return openingBalance([
        ['kind' => 'receivable', 'contract_id' => test()->first->id, 'amount' => 800_000, 'note' => 'Sewa September'],
        ['kind' => 'deposit', 'contract_id' => test()->first->id, 'amount' => 1_200_000],
        ['kind' => 'deposit', 'contract_id' => test()->second->id, 'amount' => 1_000_000],
        ['kind' => 'credit', 'contract_id' => test()->second->id, 'amount' => 150_000],
        ['kind' => 'cash', 'account_id' => Account::system(AccountSubtype::Cash)->id, 'amount' => 2_000_000],
        ['kind' => 'cash', 'account_id' => test()->bank->ledger_account_id, 'amount' => 5_000_000],
    ]);
}

it('keeps a draft out of the ledgers until it is posted', function () {
    standardOpening();

    expect(Invoice::query()->count())->toBe(0)
        ->and(DepositLedger::balance($this->first->id))->toBe(0)
        ->and(LedgerScenario::balance(AccountSubtype::OpeningEquity))->toBe(0);
});

it('carries arrears, deposits, credit, and cash in on the cut-off date with one opening journal', function () {
    $balance = app(PostOpeningBalance::class)->handle(standardOpening());

    expect($balance->status)->toBeInstanceOf(Posted::class)
        ->and($balance->posted_by)->toBe($this->owner->id)
        ->and($balance->journal_entry_id)->not->toBeNull();

    $invoice = Invoice::query()->sole();
    expect($invoice->type)->toBe(InvoiceType::Opening)
        ->and($invoice->contract_id)->toBe($this->first->id)
        ->and($invoice->issue_date->toDateString())->toBe('2026-09-30')
        ->and($invoice->balance_amount)->toBe(800_000)
        ->and(BillingScenario::lines($invoice))->toBe([['rent', 800_000]]);

    expect(DepositLedger::balance($this->first->id))->toBe(1_200_000)
        ->and(DepositLedger::balance($this->second->id))->toBe(1_000_000)
        ->and(CreditLedger::balance($this->second->id))->toBe(150_000);

    $entry = $balance->journalEntry()->firstOrFail();
    expect($entry->event)->toBe(JournalEvent::OpeningBalance)
        ->and($entry->entry_date->toDateString())->toBe('2026-09-30')
        ->and(LedgerScenario::balance(AccountSubtype::Receivable, $this->first->id))->toBe(800_000)
        ->and(LedgerScenario::balance(AccountSubtype::DepositLiability))->toBe(2_200_000)
        ->and(LedgerScenario::balance(AccountSubtype::CreditLiability, $this->second->id))->toBe(150_000)
        ->and(LedgerScenario::balance(AccountSubtype::Cash))->toBe(2_000_000)
        ->and(LedgerScenario::account($this->bank->ledger_account_id))->toBe(5_000_000)
        ->and(LedgerScenario::balance(AccountSubtype::OpeningEquity))->toBe(800_000 - 2_200_000 - 150_000 + 7_000_000);
});

it('lets a payment settle the opening arrears like any invoice', function () {
    app(PostOpeningBalance::class)->handle(standardOpening());

    PaymentScenario::transfer($this->first, 800_000);

    expect(Invoice::query()->sole()->balance_amount)->toBe(0)
        ->and(LedgerScenario::balance(AccountSubtype::Receivable, $this->first->id))->toBe(0);
});

it('does not bill the deposit of an imported contract, and bills from the agreed period', function () {
    [$invoice] = BillingScenario::issueDue($this->first);

    expect($invoice->period_start->toDateString())->toBe('2026-10-01')
        ->and(BillingScenario::lines($invoice))->toBe([['rent', 1_200_000]]);
});

it('keeps opening arrears exact, free of penalties, and not voidable', function () {
    BillingScenario::settings($this->first->property()->firstOrFail(), [
        'penalty_type' => PenaltyType::Flat->value, 'penalty_amount' => 50_000, 'grace_days' => 0, 'rounding_unit' => 1000,
    ]);

    app(PostOpeningBalance::class)->handle(openingBalance([
        ['kind' => 'receivable', 'contract_id' => $this->first->id, 'amount' => 812_345],
    ]));
    $invoice = Invoice::query()->sole();

    expect($invoice->items_total_amount)->toBe(812_345)
        ->and(app(AccrueInvoicePenalties::class)->handle($invoice))->toBe([]);

    expect(fn () => app(VoidInvoice::class)->handle($invoice, ['reason' => 'Salah input']))
        ->toThrow(ValidationException::class, 'Tunggakan saldo awal tidak bisa dibatalkan');
});

it('refuses lines that do not add up to a clean opening', function (Closure $lines, string $message) {
    expect(fn () => openingBalance($lines()))->toThrow(ValidationException::class, $message);
})->with([
    'the same contract twice' => [fn () => [
        ['kind' => 'deposit', 'contract_id' => test()->first->id, 'amount' => 1],
        ['kind' => 'deposit', 'contract_id' => test()->first->id, 'amount' => 2],
    ], 'dicatat dua kali'],
    'arrears and credit on one contract' => [fn () => [
        ['kind' => 'receivable', 'contract_id' => test()->first->id, 'amount' => 1],
        ['kind' => 'credit', 'contract_id' => test()->first->id, 'amount' => 2],
    ], 'tunggakan dan saldo kredit sekaligus'],
    'a deposit on a contract made in KostPilot' => [fn () => [
        ['kind' => 'deposit', 'contract_id' => LeaseScenario::active()->id, 'amount' => 1],
    ], 'Deposit awal hanya untuk kontrak yang diimpor'],
    'cash on an account that is not cash or bank' => [fn () => [
        ['kind' => 'cash', 'account_id' => Account::system(AccountSubtype::Receivable)->id, 'amount' => 1],
    ], 'Pilih akun kas atau rekening'],
]);

it('refuses a cut-off date in the future', function () {
    expect(fn () => openingBalance([['kind' => 'deposit', 'contract_id' => $this->first->id, 'amount' => 1]], '2026-10-06'))
        ->toThrow(ValidationException::class, 'Tanggal cut-off tidak boleh di masa depan');
});

it('refuses to carry the same deposit in twice across opening balances', function () {
    app(PostOpeningBalance::class)->handle(openingBalance([['kind' => 'deposit', 'contract_id' => $this->first->id, 'amount' => 1_200_000]]));

    expect(fn () => openingBalance([['kind' => 'deposit', 'contract_id' => $this->first->id, 'amount' => 1_200_000]]))
        ->toThrow(ValidationException::class, 'sudah pernah dicatat');
});

it('locks a posted opening balance', function () {
    $balance = app(PostOpeningBalance::class)->handle(standardOpening());

    expect(fn () => app(PostOpeningBalance::class)->handle($balance))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteOpeningBalance::class)->handle($balance))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveOpeningBalance::class)->handle(['cutoff_date' => '2026-09-01', 'lines' => []], $balance))->toThrow(AuthorizationException::class);
});

it('lets only the owner enter opening balances', function () {
    loginAs(staff(Role::Manager, $this->owner->tenant()->firstOrFail()));

    expect(fn () => standardOpening())->toThrow(AuthorizationException::class);
});

it('edits and discards a draft', function () {
    $balance = standardOpening();

    app(SaveOpeningBalance::class)->handle([
        'cutoff_date' => '2026-09-15',
        'lines' => [['kind' => 'deposit', 'contract_id' => $this->first->id, 'amount' => 900_000]],
    ], $balance);

    expect($balance->refresh()->cutoff_date->toDateString())->toBe('2026-09-15')
        ->and($balance->lines()->count())->toBe(1);

    app(DeleteOpeningBalance::class)->handle($balance);

    expect(OpeningBalance::query()->count())->toBe(0);
});
