<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Filament\App\Resources\Accounts\Pages\AccountLedger;
use App\Modules\Finance\Filament\App\Resources\Accounts\Pages\ListAccounts;
use App\Modules\Finance\Filament\App\Resources\Expenses\Pages\ListExpenses;
use App\Modules\Finance\Filament\App\Resources\Expenses\Pages\RecordExpense;
use App\Modules\Finance\Filament\App\Resources\Journals\Pages\ListJournalEntries;
use App\Modules\Finance\Filament\App\Resources\Journals\Pages\ViewJournalEntry;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\JournalEntry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\PaymentScenario;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->travelTo('2026-09-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->contract = LeaseScenario::active();
    BillingScenario::issueDue($this->contract);
    PaymentScenario::transfer($this->contract, 2_400_000);
});

it('shows the chart of accounts with a balanced trial balance and adds an account', function () {
    Livewire::test(ListAccounts::class)
        ->assertSee('Neraca saldo seimbang')
        ->assertSee(['Piutang penghuni', 'Utang deposit penghuni'])
        ->callAction('createAccount', ['kind' => 'expense', 'name' => 'Beban keamanan', 'code' => '5-2000'])
        ->assertNotified('Akun ditambahkan');

    expect(Account::query()->where('code', '5-2000')->sole()->name)->toBe('Beban keamanan');
});

it('shows an account ledger with a running balance', function () {
    Livewire::test(AccountLedger::class, ['record' => Account::system(AccountSubtype::Receivable)->getRouteKey()])
        ->assertSee('Saldo Rp0.')
        ->assertSee(['Tagihan INV/2026/09/0001', 'Rp1.200.000']);
});

it('lists journals and shows their lines', function () {
    $entry = JournalEntry::query()->where('event', JournalEvent::PaymentVerified->value)->sole();

    Livewire::test(ListJournalEntries::class)->assertCanSeeTableRecords([$entry]);

    Livewire::test(ViewJournalEntry::class, ['record' => $entry->getRouteKey()])
        ->assertSee(['Utang deposit penghuni', 'Piutang penghuni', 'Rp2.400.000']);
});

it('records an expense from the form and voids it from the list', function () {
    Livewire::test(RecordExpense::class)
        ->fillForm([
            'property_id' => $this->contract->property_id,
            'expense_account_id' => Account::system(AccountSubtype::UtilityExpense)->id,
            'description' => 'Token listrik lorong',
            'amount' => '350000',
            'paid_from_account_id' => Account::system(AccountSubtype::Cash)->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $expense = Expense::query()->sole();

    Livewire::test(ListExpenses::class)
        ->assertCanSeeTableRecords([$expense])
        ->callAction(TestAction::make('void')->table($expense), ['reason' => 'Dicatat dua kali'])
        ->assertHasNoFormErrors();

    expect($expense->refresh()->isVoided())->toBeTrue();
});

it('keeps the ledger from staff without finance access', function () {
    loginAs(staff(Role::Caretaker, $this->owner->tenant()->firstOrFail()));

    $this->get(ListAccounts::getUrl(panel: 'app'))->assertForbidden();
    $this->get(ListJournalEntries::getUrl(panel: 'app'))->assertForbidden();
});
