<?php

namespace Tests\Support;

use App\Modules\Lease\Actions\ActivateContract;
use App\Modules\Lease\Actions\CreateContract;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Resident;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomType;

/**
 * Builders for contract tests. Call inside a tenant context with an acting
 * user who may manage contracts (see loginAs()).
 */
final class LeaseScenario
{
    public static function room(int $capacity = 1): Room
    {
        $roomType = RoomType::factory()->create(['default_capacity' => $capacity]);

        return Room::factory()->forType($roomType)->create(['capacity' => $capacity]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function draft(?Room $room = null, array $overrides = []): Contract
    {
        $room ??= self::room();

        return app(CreateContract::class)->handle([
            'room_id' => $room->id,
            'resident_ids' => [Resident::factory()->create()->id],
            'payer' => 'self',
            'rental_period' => 'monthly',
            'rent_amount' => 1_200_000,
            'deposit_amount' => 1_200_000,
            'start_date' => '2026-09-20',
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function active(?Room $room = null, array $overrides = []): Contract
    {
        return app(ActivateContract::class)->handle(self::draft($room, $overrides));
    }
}
