<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Finance\Actions\CloseFiscalPeriod;
use App\Modules\Finance\Actions\PostManualJournal;
use App\Modules\Finance\Actions\RecordExpense;
use App\Modules\Finance\Actions\ReopenFiscalPeriod;
use App\Modules\Finance\Actions\ReverseManualJournal;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\CashActivity;
use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\FiscalPeriod;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\States\FiscalPeriod\Closed;
use App\Modules\Finance\States\FiscalPeriod\Open;
use App\Modules\Finance\Support\CashMovement;
use App\Modules\Finance\Support\FinancialStatements;
use App\Modules\Finance\Support\StatementLine;
use App\Modules\Property\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\PaymentScenario;

beforeEach(function () {
    $this->travelTo('2026-10-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->property = Property::factory()->create();
});

function account(string $name): Account
{
    return Account::query()->where('name', $name)->firstOrFail();
}

/**
 * @param  list<array{0: Account, 1: int, 2: int}>  $lines  account, debit, credit
 */
function manualJournal(string $date, array $lines, ?Property $property = null, string $description = 'Jurnal uji'): JournalEntry
{
    return app(PostManualJournal::class)->handle([
        'entry_date' => $date,
        'description' => $description,
        'property_id' => $property?->id,
        'lines' => array_map(fn (array $line): array => ['account_id' => $line[0]->id, 'debit' => $line[1], 'credit' => $line[2]], $lines),
    ]);
}

/**
 * @param  list<StatementLine|CashMovement>  $lines
 * @return array<string, int>
 */
function amounts(array $lines): array
{
    $amounts = [];

    foreach ($lines as $line) {
        $amounts[$line->label] = $line->amount;
    }

    return $amounts;
}

it('posts a balanced manual journal', function () {
    $entry = manualJournal('2026-10-10', [
        [account('Beban lain-lain'), 25_000, 0],
        [account('Bank dan e-wallet'), 0, 25_000],
    ], $this->property, 'Biaya administrasi bank');

    expect($entry->event)->toBe(JournalEvent::Manual)
        ->and($entry->description)->toBe('Biaya administrasi bank')
        ->and((int) $entry->lines()->sum('debit_amount'))->toBe(25_000)
        ->and((int) $entry->lines()->sum('credit_amount'))->toBe(25_000)
        ->and($entry->lines()->pluck('property_id')->unique()->all())->toBe([$this->property->id]);
});

it('refuses an unbalanced manual journal', function () {
    manualJournal('2026-10-10', [
        [account('Beban lain-lain'), 25_000, 0],
        [account('Kas'), 0, 20_000],
    ]);
})->throws(ValidationException::class, 'Total debit dan kredit harus sama. Selisihnya 5.000 rupiah.');

it('refuses a line with both debit and credit', function () {
    manualJournal('2026-10-10', [
        [account('Beban lain-lain'), 25_000, 25_000],
        [account('Kas'), 0, 0],
    ]);
})->throws(ValidationException::class, 'Isi debit atau kredit saja');

it('keeps accounts tied to invoices, payments, and deposits out of manual journals', function () {
    manualJournal('2026-10-10', [
        [account('Piutang penghuni'), 100_000, 0],
        [account('Pendapatan lain-lain'), 0, 100_000],
    ]);
})->throws(ValidationException::class, 'Akun Piutang penghuni berubah lewat tagihan');

it('refuses a manual journal dated in the future', function () {
    manualJournal('2026-10-16', [
        [account('Beban lain-lain'), 1_000, 0],
        [account('Kas'), 0, 1_000],
    ]);
})->throws(ValidationException::class);

it('keeps manual journals to owners and accountants', function () {
    loginAs(staff(Role::Manager, tenancy()->tenant()));

    manualJournal('2026-10-10', [
        [account('Beban lain-lain'), 1_000, 0],
        [account('Kas'), 0, 1_000],
    ]);
})->throws(AuthorizationException::class);

it('reverses a manual journal once', function () {
    $entry = manualJournal('2026-10-10', [
        [account('Beban lain-lain'), 25_000, 0],
        [account('Kas'), 0, 25_000],
    ]);

    $reversal = app(ReverseManualJournal::class)->handle($entry, ['entry_date' => '2026-10-15', 'reason' => 'Salah akun']);

    expect($reversal->reversal_of_id)->toBe($entry->id)
        ->and($reversal->description)->toBe("Pembalikan {$entry->number}: Salah akun")
        ->and($reversal->lines()->where('account_id', account('Kas')->id)->value('debit_amount'))->toBe(25_000)
        ->and(fn () => app(ReverseManualJournal::class)->handle($entry, ['entry_date' => '2026-10-15', 'reason' => 'Dibalik lagi']))
        ->toThrow(ValidationException::class, 'sudah dibalik');
});

it('does not reverse automatic journals from the manual screen', function () {
    app(RecordExpense::class)->handle($this->property, [
        'expense_account_id' => account('Beban kebersihan')->id,
        'paid_from_account_id' => account('Kas')->id,
        'amount' => 50_000,
        'spent_on' => '2026-10-10',
        'description' => 'Sapu',
    ]);

    app(ReverseManualJournal::class)->handle(JournalEntry::query()->sole(), ['entry_date' => '2026-10-15', 'reason' => 'Coba balik']);
})->throws(ValidationException::class, 'Hanya jurnal manual');

it('closes an ended month and refuses journals dated in it', function () {
    $period = app(CloseFiscalPeriod::class)->handle(['year' => 2026, 'month' => 9]);

    expect($period->status)->toBeInstanceOf(Closed::class)
        ->and($period->closed_by)->toBe($this->owner->id)
        ->and(fn () => manualJournal('2026-09-30', [[account('Beban lain-lain'), 1_000, 0], [account('Kas'), 0, 1_000]]))
        ->toThrow(ValidationException::class, 'Periode September 2026 sudah ditutup');

    manualJournal('2026-10-01', [[account('Beban lain-lain'), 1_000, 0], [account('Kas'), 0, 1_000]]);
});

it('does not close a month that has not ended', function () {
    app(CloseFiscalPeriod::class)->handle(['year' => 2026, 'month' => 10]);
})->throws(ValidationException::class, 'belum berakhir');

it('lets only the owner reopen a month, with a reason', function () {
    $period = app(CloseFiscalPeriod::class)->handle(['year' => 2026, 'month' => 9]);

    loginAs(staff(Role::Accountant, tenancy()->tenant()));
    expect(fn () => app(ReopenFiscalPeriod::class)->handle($period, ['reason' => 'Ada transfer yang terlewat']))
        ->toThrow(AuthorizationException::class);

    loginAs($this->owner);
    expect(fn () => app(ReopenFiscalPeriod::class)->handle($period, ['reason' => 'Lupa']))->toThrow(ValidationException::class);

    $reopened = app(ReopenFiscalPeriod::class)->handle($period, ['reason' => 'Ada transfer yang terlewat']);

    expect($reopened->status)->toBeInstanceOf(Open::class)
        ->and($reopened->reopen_reason)->toBe('Ada transfer yang terlewat')
        ->and(FiscalPeriod::query()->sole()->reopened_by)->toBe($this->owner->id);
});

it('builds profit and loss, balance sheet, and cash movements from the journal', function () {
    $other = Property::factory()->create();
    $kas = account('Kas');

    manualJournal('2026-10-01', [[$kas, 5_000_000, 0], [account('Ekuitas saldo awal'), 0, 5_000_000]]);
    manualJournal('2026-10-05', [[$kas, 1_000_000, 0], [account('Pendapatan lain-lain'), 0, 1_000_000]], $this->property);
    manualJournal('2026-10-06', [[account('Beban kebersihan'), 200_000, 0], [$kas, 0, 200_000]], $this->property);
    manualJournal('2026-10-07', [[account('Beban internet'), 300_000, 0], [$kas, 0, 300_000]], $other);
    manualJournal('2026-09-20', [[account('Beban internet'), 50_000, 0], [$kas, 0, 50_000]], $other);

    $from = CarbonImmutable::parse('2026-10-01');
    $to = CarbonImmutable::parse('2026-10-31');

    $all = FinancialStatements::profitAndLoss($from, $to, null);
    expect(amounts($all['revenue']))->toEqual(['Pendapatan lain-lain' => 1_000_000])
        ->and(amounts($all['expense']))->toEqual(['Beban kebersihan' => 200_000, 'Beban internet' => 300_000]);

    $one = FinancialStatements::profitAndLoss($from, $to, $this->property->id);
    expect(amounts($one['expense']))->toEqual(['Beban kebersihan' => 200_000]);

    $sheet = FinancialStatements::balanceSheet($to, null);
    expect(amounts($sheet['asset']))->toEqual(['Kas' => 5_450_000])
        ->and(amounts($sheet['equity']))->toEqual(['Ekuitas saldo awal' => 5_000_000])
        ->and($sheet['earnings'])->toBe(450_000);

    $cash = FinancialStatements::cashMovements($from, $to, null);
    $byActivity = fn (CashActivity $activity): array => amounts(array_values(array_filter($cash['movements'], fn (CashMovement $movement): bool => $movement->activity === $activity)));

    expect($cash['opening'])->toBe(-50_000)
        ->and($cash['closing'])->toBe(5_450_000)
        ->and($byActivity(CashActivity::Operating))->toEqual(['Pendapatan lain-lain' => 1_000_000, 'Beban kebersihan' => -200_000, 'Beban internet' => -300_000])
        ->and($byActivity(CashActivity::Financing))->toEqual(['Ekuitas saldo awal' => 5_000_000]);
});

it('names resident payments and ignores transfers between cash accounts', function () {
    $contract = LeaseScenario::active();
    BillingScenario::issueDue($contract);
    PaymentScenario::transfer($contract, 1_000_000);

    $cash = FinancialStatements::cashMovements(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'), null);

    expect(amounts($cash['movements']))->toEqual(['Pembayaran dari penghuni' => 1_000_000])
        ->and($cash['closing'])->toBe(1_000_000);
});

it('counts go-live opening balances as opening cash, not as money in', function () {
    $kas = Account::system(AccountSubtype::Cash);

    manualJournal('2026-10-01', [[$kas, 2_000_000, 0], [account('Ekuitas saldo awal'), 0, 2_000_000]]);
    JournalEntry::query()->update(['event' => JournalEvent::OpeningBalance->value]);

    $cash = FinancialStatements::cashMovements(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'), null);

    expect($cash['opening'])->toBe(2_000_000)
        ->and($cash['movements'])->toBe([])
        ->and($cash['closing'])->toBe(2_000_000);
});
