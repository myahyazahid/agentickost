<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Actions\CreateProperty;
use App\Modules\Property\Actions\CreateRoom;
use App\Modules\Property\Actions\CreateRoomType;
use App\Modules\Property\Actions\DeleteProperty;
use App\Modules\Property\Actions\DeleteRoom;
use App\Modules\Property\Actions\DeleteRoomType;
use App\Modules\Property\Actions\SetRoomPrice;
use App\Modules\Property\Actions\UpdateRoom;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomType;
use App\Modules\Property\States\Room\Available;
use App\Modules\Property\States\Room\Occupied;
use App\Modules\Property\Support\RoomPricing;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('lets an owner set up a property with room types, rooms, and prices', function () {
    loginAs(staff(Role::Owner));

    $property = app(CreateProperty::class)->handle([
        'name' => 'Kost Melati', 'code' => 'MLT', 'address' => 'Jl. Melati 5', 'city' => 'Bandung',
        'province' => 'Jawa Barat', 'timezone' => 'Asia/Jakarta', 'gender_policy' => 'female',
    ]);
    $standard = app(CreateRoomType::class)->handle($property, ['name' => 'Standar', 'default_capacity' => 1]);
    $shared = app(CreateRoomType::class)->handle($property, ['name' => 'Berdua', 'default_capacity' => 2]);
    app(SetRoomPrice::class)->handle($standard, ['rental_period' => 'monthly', 'amount' => 1_200_000, 'effective_from' => now()->toDateString()]);
    app(SetRoomPrice::class)->handle($shared, ['rental_period' => 'monthly', 'amount' => 1_800_000, 'effective_from' => now()->toDateString()]);

    $single = app(CreateRoom::class)->handle($property, ['room_type_id' => $standard->id, 'number' => '101', 'floor' => '1']);
    $double = app(CreateRoom::class)->handle($property, ['room_type_id' => $shared->id, 'number' => '201', 'floor' => '2']);

    $pricing = app(RoomPricing::class);
    expect($property->rooms()->count())->toBe(2)
        ->and($single->status)->toBeInstanceOf(Available::class)
        ->and($double->capacity)->toBe(2)
        ->and($pricing->priceFor($single, RentalPeriod::Monthly, now()))->toBe(1_200_000)
        ->and($pricing->priceFor($double, RentalPeriod::Monthly, now()))->toBe(1_800_000);
});

it('lets a manager add rooms only to an assigned property', function () {
    $owner = staff(Role::Owner);
    $tenant = $owner->tenant()->firstOrFail();
    $manager = staff(Role::Manager, $tenant);
    [$assigned, $other] = tenancy()->run($tenant, fn () => [RoomType::factory()->create(), RoomType::factory()->create()]);
    loginAs($owner);
    app(AssignStaffToProperty::class)->handle($assigned->property()->firstOrFail(), $manager);
    loginAs($manager);

    $room = app(CreateRoom::class)->handle($assigned->property()->firstOrFail(), ['room_type_id' => $assigned->id, 'number' => '101']);

    expect($room->exists)->toBeTrue()
        ->and(fn () => app(CreateRoom::class)->handle($other->property()->firstOrFail(), ['room_type_id' => $other->id, 'number' => '101']))
        ->toThrow(AuthorizationException::class);
});

it('keeps room numbers unique within a property only', function () {
    loginAs(staff(Role::Owner));
    [$first, $second] = [RoomType::factory()->create(), RoomType::factory()->create()];
    app(CreateRoom::class)->handle($first->property()->firstOrFail(), ['room_type_id' => $first->id, 'number' => '101']);

    $sameNumberElsewhere = app(CreateRoom::class)->handle($second->property()->firstOrFail(), ['room_type_id' => $second->id, 'number' => '101']);

    expect($sameNumberElsewhere->number)->toBe('101')
        ->and(fn () => app(CreateRoom::class)->handle($first->property()->firstOrFail(), ['room_type_id' => $first->id, 'number' => '101']))
        ->toThrow(ValidationException::class);
});

it('refuses a room type from another property', function () {
    loginAs(staff(Role::Owner));
    [$roomType, $otherProperty] = [RoomType::factory()->create(), Property::factory()->create()];

    app(CreateRoom::class)->handle($otherProperty, ['room_type_id' => $roomType->id, 'number' => '101']);
})->throws(ValidationException::class);

it('uses the room type capacity unless the room sets its own', function () {
    loginAs(staff(Role::Owner));
    $roomType = RoomType::factory()->create(['default_capacity' => 2]);
    $room = app(CreateRoom::class)->handle($roomType->property()->firstOrFail(), ['room_type_id' => $roomType->id, 'number' => '101']);

    app(UpdateRoom::class)->handle($room, ['room_type_id' => $roomType->id, 'number' => '101', 'capacity' => 3]);

    expect($room->fresh()?->capacity)->toBe(3);
});

it('keeps rooms from caretakers', function () {
    $caretaker = staff(Role::Caretaker);
    $roomType = tenancy()->run($caretaker->tenant()->firstOrFail(), fn () => RoomType::factory()->create());
    loginAs($caretaker);

    app(CreateRoom::class)->handle($roomType->property()->firstOrFail(), ['room_type_id' => $roomType->id, 'number' => '101']);
})->throws(AuthorizationException::class);

it('refuses to delete what rooms still depend on', function (Closure $delete, string $message) {
    loginAs(staff(Role::Owner));
    $room = Room::factory()->create();

    expect(fn () => $delete($room))->toThrow(ValidationException::class, $message);
})->with([
    'a property with rooms' => [fn (Room $room) => app(DeleteProperty::class)->handle($room->property()->firstOrFail()), 'Properti masih punya kamar.'],
    'a room type with rooms' => [fn (Room $room) => app(DeleteRoomType::class)->handle($room->roomType()->firstOrFail()), 'Tipe ini masih dipakai kamar.'],
]);

it('refuses to delete a room that is not available', function () {
    loginAs(staff(Role::Owner));
    $room = Room::factory()->create();
    $room->status = new Occupied($room);
    $room->saveQuietly();

    app(DeleteRoom::class)->handle($room);
})->throws(ValidationException::class, 'Hanya kamar berstatus Tersedia yang bisa dihapus.');
