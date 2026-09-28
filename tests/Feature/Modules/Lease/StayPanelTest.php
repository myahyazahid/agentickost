<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Models\Account;
use App\Modules\Lease\Actions\GiveNotice;
use App\Modules\Lease\Filament\App\Resources\Contracts\Pages\ViewContract;
use App\Modules\Lease\Filament\App\Resources\Contracts\RelationManagers\InspectionsRelationManager;
use App\Modules\Lease\Models\Inspection;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Lease\States\Contract\Completed;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Models\Room;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\PaymentScenario;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->travelTo('2026-09-20 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->room = LeaseScenario::room();
});

it('checks in from the contract page with the default checklist', function () {
    $contract = LeaseScenario::draft($this->room);

    Livewire::test(ViewContract::class, ['record' => $contract->getRouteKey()])
        ->mountAction('checkIn')
        ->assertSee('Kasur dan dipan')
        ->callMountedAction()
        ->assertNotified('Check-in dicatat');

    expect($contract->refresh()->status)->toBeInstanceOf(Active::class)
        ->and(Inspection::query()->sole()->items()->count())->toBe(8);

    Livewire::test(InspectionsRelationManager::class, ['ownerRecord' => $contract, 'pageClass' => ViewContract::class])
        ->callAction(TestAction::make('acknowledge')->table(Inspection::query()->sole()))
        ->assertHasNoFormErrors();

    expect(Inspection::query()->sole()->resident_acknowledged_at)->not->toBeNull();
});

it('moves a resident to another room from the contract page', function () {
    $contract = LeaseScenario::active($this->room, ['start_date' => '2026-09-01']);
    $other = Room::factory()->forType($this->room->roomType()->firstOrFail())->create(['number' => 'C3', 'capacity' => 1]);

    Livewire::test(ViewContract::class, ['record' => $contract->getRouteKey()])
        ->callAction('moveRoom', ['to_room_id' => $other->id, 'moved_on' => '2026-09-20', 'new_rent_amount' => '1500000'])
        ->assertNotified('Penghuni dipindah ke kamar baru')
        ->assertSee('Riwayat pindah kamar');

    expect($contract->refresh()->room_id)->toBe($other->id);
});

it('checks out and lets the owner settle it from the contract page', function () {
    $contract = LeaseScenario::active($this->room, ['start_date' => '2026-09-01']);
    $this->travelTo('2026-09-01 03:00:00');
    BillingScenario::issueDue($contract);
    PaymentScenario::transfer($contract, 2_400_000);
    $this->travelTo('2026-09-20 03:00:00');
    app(GiveNotice::class)->handle($contract, ['planned_move_out_on' => '2026-09-30']);
    $caretaker = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->room->property()->firstOrFail(), $caretaker);
    loginAs($caretaker);

    Livewire::test(ViewContract::class, ['record' => $contract->getRouteKey()])
        ->assertActionHidden('finalizeSettlement')
        ->callAction('checkOut', [
            'moved_out_on' => '2026-09-20',
            'early_termination_amount' => '0',
            'room_after' => 'available',
            'items' => [['item_name' => 'Lemari', 'condition' => 'damaged', 'charge_amount' => '200000']],
        ])
        ->assertNotified('Check-out dicatat, penyelesaian menunggu owner');

    loginAs($this->owner);

    Livewire::test(ViewContract::class, ['record' => $contract->getRouteKey()])
        ->assertSee('Penyelesaian check-out')
        ->callAction('finalizeSettlement', ['refund_account_id' => Account::system(AccountSubtype::Cash)->id])
        ->assertNotified('Check-out selesai');

    expect($contract->refresh()->status)->toBeInstanceOf(Completed::class)
        ->and($contract->settlement()->firstOrFail()->result_amount)->toBe(-1_000_000);
});
