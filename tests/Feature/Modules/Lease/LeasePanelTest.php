<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Lease\Filament\App\Resources\Contracts\Pages\CreateContract;
use App\Modules\Lease\Filament\App\Resources\Contracts\Pages\ViewContract;
use App\Modules\Lease\Filament\App\Resources\Contracts\RelationManagers\OccupantsRelationManager;
use App\Modules\Lease\Filament\App\Resources\Residents\Pages\CreateResident;
use App\Modules\Lease\Filament\App\Resources\Residents\Pages\ListResidents;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Lease\States\Contract\Notice;
use App\Modules\Property\Actions\SetRoomPrice;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->travelTo('2026-09-15 03:00:00');
    loginAs(staff(Role::Owner));
});

it('registers a resident from the form', function () {
    Livewire::test(CreateResident::class)
        ->fillForm(['full_name' => 'Rina Kartika', 'phone' => '0812 3456 7890'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Resident::query()->where('full_name', 'Rina Kartika')->sole()->phone)->toBe('+6281234567890');
});

it('shows an invalid phone number on the phone field', function () {
    Livewire::test(CreateResident::class)
        ->fillForm(['full_name' => 'Rina Kartika', 'phone' => '12'])
        ->call('create')
        ->assertHasFormErrors(['phone']);
});

it('marks residents flagged as not recommended in the list', function () {
    Resident::factory()->create(['full_name' => 'Doni', 'is_flagged' => true]);

    Livewire::test(ListResidents::class)->assertSee('Tidak disarankan');
});

it('fills the rent from the room price and drafts the contract', function () {
    $room = LeaseScenario::room();
    app(SetRoomPrice::class)->handle($room->roomType()->firstOrFail(), [
        'rental_period' => 'monthly', 'amount' => 1_350_000, 'effective_from' => '2026-01-01',
    ]);
    $resident = Resident::factory()->create();

    Livewire::test(CreateContract::class)
        ->fillForm(['property_id' => $room->property_id])
        ->fillForm(['room_id' => $room->id])
        ->assertSchemaStateSet(['rent_amount' => 1_350_000, 'deposit_amount' => 1_350_000])
        ->fillForm(['resident_ids' => [$resident->id], 'payer' => 'self'])
        ->call('create')
        ->assertHasNoFormErrors();

    $contract = Contract::query()->where('room_id', $room->id)->sole();
    expect($contract->rent_amount)->toBe(1_350_000)
        ->and($contract->primaryResident()?->id)->toBe($resident->id);
});

it('activates a draft and records a planned move-out from the contract page', function () {
    $contract = LeaseScenario::draft();

    Livewire::test(ViewContract::class, ['record' => $contract->getRouteKey()])
        ->callAction('activate')
        ->assertHasNoFormErrors();
    expect($contract->fresh()?->status)->toBeInstanceOf(Active::class);

    Livewire::test(ViewContract::class, ['record' => $contract->getRouteKey()])
        ->callAction('giveNotice', ['notice_given_on' => '2026-09-15', 'planned_move_out_on' => '2026-10-31'])
        ->assertHasNoFormErrors();
    expect($contract->fresh()?->status)->toBeInstanceOf(Notice::class);
});

it('renews with the prefilled current room price and deposit', function () {
    $room = LeaseScenario::room();
    $contract = LeaseScenario::active($room, ['end_date' => '2026-12-19', 'deposit_amount' => 1_000_000]);
    app(SetRoomPrice::class)->handle($room->roomType()->firstOrFail(), [
        'rental_period' => 'monthly', 'amount' => 1_450_000, 'effective_from' => '2026-12-01',
    ]);

    Livewire::test(ViewContract::class, ['record' => $contract->getRouteKey()])
        ->callAction('renew')
        ->assertHasNoFormErrors();

    $renewal = $contract->renewal()->firstOrFail();
    expect($renewal->rent_amount)->toBe(1_450_000)
        ->and($renewal->deposit_amount)->toBe(1_000_000);
});

it('reports a refused action as a notification and keeps the contract as it was', function () {
    $contract = LeaseScenario::active();

    Livewire::test(ViewContract::class, ['record' => $contract->getRouteKey()])
        ->callAction('giveNotice', ['notice_given_on' => '2026-09-15', 'planned_move_out_on' => '2026-09-01'])
        ->assertNotified('Belum bisa diproses');

    expect($contract->fresh()?->status)->toBeInstanceOf(Active::class);
});

it('adds a resident to a shared room from the contract page', function () {
    $contract = LeaseScenario::active(LeaseScenario::room(capacity: 2));
    $roommate = Resident::factory()->create();

    Livewire::test(OccupantsRelationManager::class, ['ownerRecord' => $contract, 'pageClass' => ViewContract::class])
        ->callAction(TestAction::make('addResident')->table(), ['resident_id' => $roommate->id, 'joined_on' => '2026-10-01'])
        ->assertHasNoFormErrors();

    expect($contract->occupants()->whereNull('left_on')->count())->toBe(2);
});
