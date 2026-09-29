<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Finance\Actions\PostManualJournal;
use App\Modules\Finance\Filament\App\Pages\DepositsHeld;
use App\Modules\Finance\Filament\App\Pages\FiscalPeriods;
use App\Modules\Finance\Filament\App\Resources\Journals\Pages\CreateManualJournal;
use App\Modules\Finance\Filament\App\Resources\Journals\Pages\ViewJournalEntry;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\FiscalPeriod;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\States\FiscalPeriod\Closed;
use App\Modules\Lease\Actions\ExportResidentRegister;
use App\Modules\Lease\Models\Resident;
use App\Modules\Reports\Filament\App\Pages\Arrears;
use App\Modules\Reports\Filament\App\Pages\BalanceSheet;
use App\Modules\Reports\Filament\App\Pages\CashFlow;
use App\Modules\Reports\Filament\App\Pages\CashReport;
use App\Modules\Reports\Filament\App\Pages\ProfitAndLoss;
use App\Modules\Reports\Filament\App\Pages\ReceivablesAgingReport;
use App\Modules\Reports\Filament\App\Pages\ResidentRegisterReport;
use App\Modules\Reports\Support\ReceivablesAging;
use App\Modules\Subscription\Actions\ChoosePlan;
use App\Modules\Subscription\Actions\MarkSubscriptionInvoicePaid;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Support\Actors\Actor;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    $this->travelTo('2026-10-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->tenant = tenancy()->tenant();
});

function ledgerAccount(string $name): Account
{
    return Account::query()->where('name', $name)->firstOrFail();
}

function postIncome(int $amount, string $date = '2026-10-05'): JournalEntry
{
    return app(PostManualJournal::class)->handle([
        'entry_date' => $date,
        'description' => 'Sewa parkir',
        'lines' => [
            ['account_id' => ledgerAccount('Kas')->id, 'debit' => $amount],
            ['account_id' => ledgerAccount('Pendapatan lain-lain')->id, 'credit' => $amount],
        ],
    ]);
}

it('shows the financial statements for the chosen period', function () {
    postIncome(750_000);

    Livewire::test(ProfitAndLoss::class)
        ->assertSee(['Pendapatan lain-lain', 'Rp750.000', 'Laba bersih'])
        ->set('filters.from', '2026-11-01')
        ->set('filters.to', '2026-11-30')
        ->assertDontSee('Rp750.000');

    Livewire::test(BalanceSheet::class)->assertSee(['Kas', 'Laba ditahan dan laba berjalan', 'Total kewajiban dan ekuitas']);
    Livewire::test(CashFlow::class)->assertSee(['Arus kas dari operasional', 'Kas dan bank akhir periode']);
    Livewire::test(CashReport::class)->assertSee(['Uang masuk', 'Pendapatan lain-lain', 'Uang masuk lebih banyak']);
});

it('downloads a report as Excel and PDF', function () {
    postIncome(750_000);

    Livewire::test(ProfitAndLoss::class)
        ->callAction('downloadExcel')
        ->assertFileDownloaded('laba-rugi-20261001-20261015.xlsx');

    Livewire::test(ProfitAndLoss::class)
        ->callAction('downloadPdf')
        ->assertFileDownloaded('laba-rugi-20261001-20261015.pdf');
});

it('downloads the arrears and deposit lists', function () {
    Livewire::test(Arrears::class)->callAction('downloadExcel')->assertFileDownloaded('tunggakan-20261015.xlsx');
    Livewire::test(DepositsHeld::class)->callAction('downloadPdf')->assertFileDownloaded('deposit-dipegang-20261015.pdf');
});

it('hides advanced reports on a plan without them', function () {
    $plan = Plan::factory()->create(['features' => []]);
    $invoice = app(ChoosePlan::class)->handle(['plan_id' => $plan->id, 'billing_cycle' => 'monthly']);
    $tenant = tenancy()->tenant();
    tenancy()->forget();
    actors()->actingAs(Actor::platformAdmin(PlatformAdmin::factory()->create()), fn () => app(MarkSubscriptionInvoicePaid::class)->handle($invoice, ['paid_at' => now()->toDateTimeString()]));
    loginAs($this->owner);

    $this->get(ProfitAndLoss::getUrl())->assertForbidden();
    $this->get(ReceivablesAgingReport::getUrl())->assertForbidden();

    $plan->update(['features' => ['advanced_reports']]);

    $this->get(ProfitAndLoss::getUrl())->assertOk();
    expect($tenant->id)->toBe(tenancy()->id());
});

it('keeps financial statements from staff without finance access', function () {
    loginAs(staff(Role::Manager, $this->tenant));

    $this->get(ProfitAndLoss::getUrl())->assertForbidden();
});

it('sorts unpaid balances by how long they are overdue', function () {
    expect(array_map(fn (int $days): string => ReceivablesAging::bucket($days), [-3, 0, 1, 30, 31, 60, 61]))
        ->toBe(['current', 'current', 'days_1_30', 'days_1_30', 'days_31_60', 'days_31_60', 'days_over_60']);

    $contract = LeaseScenario::active();
    $invoices = BillingScenario::issueDue($contract);
    $this->travel(45)->days();

    $today = $contract->property()->firstOrFail()->today();
    $expected = ['current' => 0, 'days_1_30' => 0, 'days_31_60' => 0, 'days_over_60' => 0];

    foreach ($invoices as $invoice) {
        $invoice->refresh();
        $expected[ReceivablesAging::bucket((int) $invoice->due_date->diffInDays($today, false))] += $invoice->balance_amount;
    }

    $rows = ReceivablesAging::rows($this->owner, null);

    expect($rows)->toHaveCount(1)
        ->and(array_intersect_key($rows[0], $expected))->toBe($expected)
        ->and($rows[0]['total'])->toBe(array_sum($expected))
        ->and($expected['days_31_60'])->toBeGreaterThan(0);

    Livewire::test(ReceivablesAgingReport::class)->assertSee(['31–60 hari', $contract->payer?->name]);
});

it('exports the resident register with identity numbers only for staff who may see them', function () {
    $contract = LeaseScenario::active();
    $resident = $contract->residents()->firstOrFail();
    Resident::query()->whereKey($resident->id)->firstOrFail()->forceFill(['identity_number' => '3201010101010001'])->save();
    $property = $contract->property()->firstOrFail();

    $rows = app(ExportResidentRegister::class)->handle($property);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe($resident->full_name)
        ->and($rows[0]['identity'])->toContain('3201010101010001')
        ->and(AuditLog::query()->where('event', 'resident.register_exported')->count())->toBe(1);

    Livewire::test(ResidentRegisterReport::class)
        ->assertSee($resident->full_name)
        ->assertDontSee('3201010101010001')
        ->callAction('downloadExcel')
        ->assertFileDownloaded();

    loginAs(staff(Role::Accountant, $this->tenant));
    $rows = app(ExportResidentRegister::class)->handle($property);

    expect((string) $rows[0]['identity'])->not->toContain('3201010101010001')
        ->and(AuditLog::query()->where('event', 'resident.register_exported')->count())->toBe(2);
});

it('enters and reverses a manual journal from the panel', function () {
    Livewire::test(CreateManualJournal::class)
        ->fillForm([
            'entry_date' => '2026-10-10',
            'description' => 'Biaya admin bank',
            'lines' => [
                ['account_id' => ledgerAccount('Beban lain-lain')->id, 'debit' => '6.500', 'credit' => null, 'memo' => null],
                ['account_id' => ledgerAccount('Bank dan e-wallet')->id, 'debit' => null, 'credit' => '6.500', 'memo' => null],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $entry = JournalEntry::query()->sole();

    Livewire::test(ViewJournalEntry::class, ['record' => $entry->getRouteKey()])
        ->callAction('reverse', ['entry_date' => '2026-10-15', 'reason' => 'Salah akun bank'])
        ->assertHasNoActionErrors()
        ->assertNotified('Jurnal dibalik');

    expect(JournalEntry::query()->count())->toBe(2);
});

it('shows a line error on the journal form', function () {
    Livewire::test(CreateManualJournal::class)
        ->fillForm([
            'entry_date' => '2026-10-10',
            'description' => 'Tidak seimbang',
            'lines' => [
                ['account_id' => ledgerAccount('Beban lain-lain')->id, 'debit' => '6.500', 'credit' => null, 'memo' => null],
                ['account_id' => ledgerAccount('Kas')->id, 'debit' => null, 'credit' => '5.000', 'memo' => null],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['lines']);

    expect(JournalEntry::query()->count())->toBe(0);
});

it('closes a month from the period list', function () {
    postIncome(100_000, '2026-09-20');

    Livewire::test(FiscalPeriods::class)
        ->assertSee(['September 2026', 'Terbuka'])
        ->callAction(TestAction::make('close')->table('2026-09'))
        ->assertNotified('Buku September 2026 ditutup');

    expect(FiscalPeriod::query()->where('month', 9)->sole()->status)->toBeInstanceOf(Closed::class);
});
