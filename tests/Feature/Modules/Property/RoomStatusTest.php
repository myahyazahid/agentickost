<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Actions\FinishRoomMaintenance;
use App\Modules\Property\Actions\StartRoomMaintenance;
use App\Modules\Property\Models\Room;
use App\Modules\Property\States\Room\Available;
use App\Modules\Property\States\Room\Booked;
use App\Modules\Property\States\Room\Held;
use App\Modules\Property\States\Room\Maintenance;
use App\Modules\Property\States\Room\Occupied;
use App\Modules\Property\States\Room\RoomState;
use App\Modules\Property\States\Room\Vacating;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

/**
 * @param  class-string<RoomState>  $status
 */
function roomIn(string $status): Room
{
    tenancy()->set(Tenant::factory()->create());

    $room = Room::factory()->create();
    $room->status = new $status($room);
    $room->saveQuietly();

    return $room->fresh() ?? throw new LogicException;
}

/*
 * PRD §9.1, plus check-out straight to available (PRD §8.9).
 */
$allowed = [
    'available to held' => [Available::class, Held::class],
    'held back to available' => [Held::class, Available::class],
    'held to booked' => [Held::class, Booked::class],
    'available to booked' => [Available::class, Booked::class],
    'booked back to available' => [Booked::class, Available::class],
    'booked to occupied' => [Booked::class, Occupied::class],
    'available to occupied' => [Available::class, Occupied::class],
    'occupied to vacating' => [Occupied::class, Vacating::class],
    'vacating back to occupied' => [Vacating::class, Occupied::class],
    'vacating to maintenance' => [Vacating::class, Maintenance::class],
    'occupied to maintenance' => [Occupied::class, Maintenance::class],
    'available to maintenance' => [Available::class, Maintenance::class],
    'maintenance to available' => [Maintenance::class, Available::class],
    'occupied to available' => [Occupied::class, Available::class],
    'vacating to available' => [Vacating::class, Available::class],
];

it('allows the transitions in PRD 9.1', function (string $from, string $to) {
    $room = roomIn($from);

    $room->status->transitionTo($to);

    expect($room->fresh()?->status)->toBeInstanceOf($to);
})->with($allowed);

it('rejects transitions outside PRD 9.1', function (string $from, string $to) {
    roomIn($from)->status->transitionTo($to);
})->with([
    'maintenance to occupied' => [Maintenance::class, Occupied::class],
    'maintenance to booked' => [Maintenance::class, Booked::class],
    'held to occupied' => [Held::class, Occupied::class],
    'booked to vacating' => [Booked::class, Vacating::class],
    'occupied to booked' => [Occupied::class, Booked::class],
    'vacating to held' => [Vacating::class, Held::class],
    'available to vacating' => [Available::class, Vacating::class],
])->throws(CouldNotPerformTransition::class);

it('rejects an invalid status assigned directly', function () {
    $room = roomIn(Maintenance::class);

    $room->status = new Occupied($room);
    $room->save();
})->throws(CouldNotPerformTransition::class);

it('lets an assigned caretaker take a room into and out of maintenance', function () {
    $owner = staff(Role::Owner);
    $tenant = $owner->tenant()->firstOrFail();
    $caretaker = staff(Role::Caretaker, $tenant);
    $room = tenancy()->run($tenant, fn () => Room::factory()->create());
    loginAs($owner);
    app(AssignStaffToProperty::class)->handle($room->property()->firstOrFail(), $caretaker);

    loginAs($caretaker);
    app(StartRoomMaintenance::class)->handle($room);
    expect($room->fresh()?->status)->toBeInstanceOf(Maintenance::class);

    app(FinishRoomMaintenance::class)->handle($room);
    expect($room->fresh()?->status)->toBeInstanceOf(Available::class);
});

it('forbids a caretaker from rooms of a property they are not assigned to', function () {
    $caretaker = staff(Role::Caretaker);
    $room = tenancy()->run($caretaker->tenant()->firstOrFail(), fn () => Room::factory()->create());
    loginAs($caretaker);

    app(StartRoomMaintenance::class)->handle($room);
})->throws(AuthorizationException::class);

it('keeps manual maintenance to available rooms only', function (Closure $act, string $message) {
    $owner = staff(Role::Owner);
    $room = tenancy()->run($owner->tenant()->firstOrFail(), fn () => Room::factory()->create());
    $room->status = new Occupied($room);
    $room->saveQuietly();
    loginAs($owner);

    expect(fn () => $act($room->fresh()))->toThrow(ValidationException::class, $message)
        ->and($room->fresh()?->status)->toBeInstanceOf(Occupied::class);
})->with([
    'start' => [fn (Room $room) => app(StartRoomMaintenance::class)->handle($room), 'Hanya kamar berstatus Tersedia yang bisa masuk perbaikan.'],
    'finish' => [fn (Room $room) => app(FinishRoomMaintenance::class)->handle($room), 'Kamar ini tidak sedang dalam perbaikan.'],
]);
