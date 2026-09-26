<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Filament\App\RelationManagers\PricesRelationManager;
use App\Modules\Property\Filament\App\Resources\Properties\Pages\CreateProperty;
use App\Modules\Property\Filament\App\Resources\Properties\Pages\EditPropertySettings;
use App\Modules\Property\Filament\App\Resources\Properties\Pages\ListProperties;
use App\Modules\Property\Filament\App\Resources\Properties\PropertyResource;
use App\Modules\Property\Filament\App\Resources\Rooms\Pages\CreateRoom;
use App\Modules\Property\Filament\App\Resources\Rooms\Pages\ListRooms;
use App\Modules\Property\Filament\App\Resources\RoomTypes\Pages\EditRoomType;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomType;
use App\Modules\Property\States\Room\Maintenance;
use App\Modules\Property\Support\RoomPricing;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

/**
 * @return array<string, mixed>
 */
function propertyForm(array $overrides = []): array
{
    return [
        'name' => 'Kost Melati', 'code' => 'MLT', 'address' => 'Jl. Melati 5', 'city' => 'Bandung',
        'province' => 'Jawa Barat', 'timezone' => 'Asia/Jakarta', 'gender_policy' => 'female',
        ...$overrides,
    ];
}

it('creates a property from the form', function () {
    loginAs(staff(Role::Owner));

    Livewire::test(CreateProperty::class)
        ->fillForm(propertyForm())
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Property::query()->where('code', 'MLT')->sole()->settings()->exists())->toBeTrue();
});

it('shows a duplicate code from the action on the code field', function () {
    loginAs(staff(Role::Owner));
    Property::factory()->create(['code' => 'MLT']);

    Livewire::test(CreateProperty::class)
        ->fillForm(propertyForm())
        ->call('create')
        ->assertHasFormErrors(['code']);
});

it('lists only the properties a manager is assigned to', function () {
    $owner = staff(Role::Owner);
    $tenant = $owner->tenant()->firstOrFail();
    $manager = staff(Role::Manager, $tenant);
    [$assigned, $other] = tenancy()->run($tenant, fn () => Property::factory()->count(2)->create()->all());
    loginAs($owner);
    app(AssignStaffToProperty::class)->handle($assigned, $manager);

    loginAs($manager);

    Livewire::test(ListProperties::class)
        ->assertCanSeeTableRecords([$assigned])
        ->assertCanNotSeeTableRecords([$other]);
});

it('opens the edit page of another property as not found for a manager', function () {
    $manager = staff(Role::Manager);
    $property = tenancy()->run($manager->tenant()->firstOrFail(), fn () => Property::factory()->create());

    $this->actingAs($manager)
        ->get(PropertyResource::getUrl('edit', ['record' => $property], panel: 'app'))
        ->assertNotFound();
});

it('saves billing rules from the settings page', function () {
    loginAs(staff(Role::Owner));
    $property = Property::factory()->create();

    Livewire::test(EditPropertySettings::class, ['record' => $property->getRouteKey()])
        ->fillForm(['billing_mode' => 'fixed_date', 'fixed_billing_day' => 1, 'penalty_type' => 'flat', 'penalty_amount' => '25.000'])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = $property->resolvedSettings();
    expect($settings->fixed_billing_day)->toBe(1)
        ->and($settings->penalty_amount)->toBe(25_000);
});

it('keeps the settings page from staff without the permission', function () {
    $owner = staff(Role::Owner);
    $tenant = $owner->tenant()->firstOrFail();
    $accountant = staff(Role::Accountant, $tenant);
    $property = tenancy()->run($tenant, fn () => Property::factory()->create());

    $this->actingAs($accountant)
        ->get(PropertyResource::getUrl('settings', ['record' => $property], panel: 'app'))
        ->assertForbidden();
});

it('creates a room of the chosen property and type', function () {
    loginAs(staff(Role::Owner));
    $roomType = RoomType::factory()->create();

    Livewire::test(CreateRoom::class)
        ->fillForm(['property_id' => $roomType->property_id, 'room_type_id' => $roomType->id, 'number' => '101'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Room::query()->where('number', '101')->sole()->room_type_id)->toBe($roomType->id);
});

it('shows rooms in the grid and takes one into maintenance', function () {
    loginAs(staff(Role::Owner));
    $room = Room::factory()->create();

    Livewire::test(ListRooms::class)
        ->assertCanSeeTableRecords([$room])
        ->assertSee('Tersedia')
        ->callAction(TestAction::make('startMaintenance')->table($room));

    expect($room->fresh()?->status)->toBeInstanceOf(Maintenance::class);
});

it('changes a room type price from its price history', function () {
    loginAs(staff(Role::Owner));
    $roomType = RoomType::factory()->create();
    $room = Room::factory()->forType($roomType)->create();

    Livewire::test(PricesRelationManager::class, ['ownerRecord' => $roomType, 'pageClass' => EditRoomType::class])
        ->callAction(TestAction::make('setPrice')->table(), [
            'rental_period' => 'monthly',
            'amount' => '1.250.000',
            'effective_from' => now()->toDateString(),
        ])
        ->assertHasNoFormErrors();

    expect(app(RoomPricing::class)->priceFor($room, RentalPeriod::Monthly, now()))->toBe(1_250_000);
});
