<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Property\Actions\EndRoomPriceOverride;
use App\Modules\Property\Actions\SetRoomPrice;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomPrice;
use App\Modules\Property\Models\RoomType;
use App\Modules\Property\Support\RoomPricing;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * @return array{0: User, 1: RoomType, 2: Room}
 */
function ownerWithRoom(): array
{
    $owner = loginAs(staff(Role::Owner));
    $roomType = RoomType::factory()->create();
    $room = Room::factory()->forType($roomType)->create();

    return [$owner, $roomType, $room];
}

function priceOn(Room $room, string $date, RentalPeriod $period = RentalPeriod::Monthly): ?int
{
    return app(RoomPricing::class)->priceFor($room, $period, CarbonImmutable::parse($date));
}

it('keeps the price history when the price changes', function () {
    [, $roomType, $room] = ownerWithRoom();

    app(SetRoomPrice::class)->handle($roomType, ['rental_period' => 'monthly', 'amount' => 1_200_000, 'effective_from' => '2026-01-01']);
    app(SetRoomPrice::class)->handle($roomType, ['rental_period' => 'monthly', 'amount' => 1_350_000, 'effective_from' => '2026-07-01']);

    $history = RoomPrice::query()->where('room_type_id', $roomType->id)->oldest('effective_from')->get();
    expect($history)->toHaveCount(2)
        ->and($history[0]->effective_until?->toDateString())->toBe('2026-06-30')
        ->and(priceOn($room, '2026-06-30'))->toBe(1_200_000)
        ->and(priceOn($room, '2026-07-01'))->toBe(1_350_000)
        ->and(priceOn($room, '2025-12-31'))->toBeNull();
});

it('keeps prices per rental period apart', function () {
    [, $roomType, $room] = ownerWithRoom();

    app(SetRoomPrice::class)->handle($roomType, ['rental_period' => 'monthly', 'amount' => 1_200_000, 'effective_from' => '2026-01-01']);
    app(SetRoomPrice::class)->handle($roomType, ['rental_period' => 'daily', 'amount' => 75_000, 'effective_from' => '2026-01-01']);

    expect(priceOn($room, '2026-03-01'))->toBe(1_200_000)
        ->and(priceOn($room, '2026-03-01', RentalPeriod::Daily))->toBe(75_000)
        ->and(priceOn($room, '2026-03-01', RentalPeriod::Weekly))->toBeNull();
});

it('uses a room override over its type price, until the override ends', function () {
    [, $roomType, $room] = ownerWithRoom();
    app(SetRoomPrice::class)->handle($roomType, ['rental_period' => 'monthly', 'amount' => 1_200_000, 'effective_from' => '2026-01-01']);

    app(SetRoomPrice::class)->handle($room, ['rental_period' => 'monthly', 'amount' => 1_500_000, 'effective_from' => '2026-02-01']);
    app(EndRoomPriceOverride::class)->handle($room, RentalPeriod::Monthly, '2026-05-01');

    expect(priceOn($room, '2026-01-31'))->toBe(1_200_000)
        ->and(priceOn($room, '2026-02-01'))->toBe(1_500_000)
        ->and(priceOn($room, '2026-04-30'))->toBe(1_500_000)
        ->and(priceOn($room, '2026-05-01'))->toBe(1_200_000);
});

it('refuses a price that starts on or before a later price already set', function () {
    [, $roomType] = ownerWithRoom();
    app(SetRoomPrice::class)->handle($roomType, ['rental_period' => 'monthly', 'amount' => 1_350_000, 'effective_from' => '2026-07-01']);

    app(SetRoomPrice::class)->handle($roomType, ['rental_period' => 'monthly', 'amount' => 1_300_000, 'effective_from' => '2026-07-01']);
})->throws(ValidationException::class, 'Sudah ada harga yang berlaku mulai tanggal ini atau sesudahnya.');

it('records who set the price', function () {
    [$owner, $roomType] = ownerWithRoom();

    $price = app(SetRoomPrice::class)->handle($roomType, ['rental_period' => 'monthly', 'amount' => 1_200_000, 'effective_from' => '2026-01-01']);

    expect($price->created_by)->toBe($owner->id);
});

it('leaves prices to the owner', function (Role $role) {
    $owner = staff(Role::Owner);
    $tenant = $owner->tenant()->firstOrFail();
    $roomType = tenancy()->run($tenant, fn () => RoomType::factory()->create());
    loginAs(staff($role, $tenant));

    app(SetRoomPrice::class)->handle($roomType, ['rental_period' => 'monthly', 'amount' => 1, 'effective_from' => '2026-01-01']);
})->with([Role::Manager, Role::Caretaker, Role::Accountant])->throws(AuthorizationException::class);
