<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\Pages\CreateOpeningBalance;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\Pages\EditOpeningBalance;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\Pages\ListOpeningBalances;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\Pages\ViewOpeningBalance;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\OpeningBalance;
use App\Modules\Finance\States\OpeningBalance\Posted;
use App\Modules\Finance\Support\DepositLedger;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\LeaseScenario;
use Tests\Support\LedgerScenario;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->travelTo('2026-10-05 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->contract = LeaseScenario::imported();
});

it('drafts an opening balance, shows the totals, then posts it', function () {
    Livewire::test(CreateOpeningBalance::class)
        ->fillForm([
            'cutoff_date' => '2026-09-30',
            'receivables' => [['contract_id' => $this->contract->id, 'amount' => '600.000', 'note' => 'Sewa September']],
            'deposits' => [['contract_id' => $this->contract->id, 'amount' => '1.200.000']],
            'cash' => [['account_id' => Account::system(AccountSubtype::Cash)->id, 'amount' => '3.000.000']],
        ])
        ->assertSee('Hasilnya, Rp2.400.000, dicatat sebagai ekuitas saldo awal.')
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Draf saldo awal tersimpan');

    $balance = OpeningBalance::query()->sole();

    expect(Invoice::query()->count())->toBe(0);

    Livewire::test(EditOpeningBalance::class, ['record' => $balance->getRouteKey()])
        ->assertFormSet(['cutoff_date' => '2026-09-30'])
        ->callAction('post')
        ->assertNotified('Saldo awal diposting')
        ->assertRedirect(ViewOpeningBalance::getUrl(['record' => $balance]));

    expect($balance->refresh()->status)->toBeInstanceOf(Posted::class)
        ->and(DepositLedger::balance($this->contract->id))->toBe(1_200_000)
        ->and(LedgerScenario::balance(AccountSubtype::OpeningEquity))->toBe(2_400_000);

    Livewire::test(ViewOpeningBalance::class, ['record' => $balance->getRouteKey()])
        ->assertSee(['Tunggakan', 'Sewa September', 'Rp600.000', $balance->journalEntry()->value('number')]);
});

it('explains a refused line without losing the form', function () {
    Livewire::test(CreateOpeningBalance::class)
        ->fillForm([
            'cutoff_date' => '2026-09-30',
            'receivables' => [['contract_id' => $this->contract->id, 'amount' => '100.000']],
            'credits' => [['contract_id' => $this->contract->id, 'amount' => '50.000']],
        ])
        ->call('create')
        ->assertNotified('Saldo awal belum bisa disimpan');

    expect(OpeningBalance::query()->count())->toBe(0);
});

it('lists opening balances for the owner and hides them from caretakers', function () {
    Livewire::test(ListOpeningBalances::class)->assertSee('Belum ada saldo awal');

    loginAs(staff(Role::Caretaker, $this->owner->tenant()->firstOrFail()));
    $this->get(ListOpeningBalances::getUrl())->assertForbidden();
});
