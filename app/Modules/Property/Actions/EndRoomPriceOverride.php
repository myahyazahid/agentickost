<?php

namespace App\Modules\Property\Actions;

use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomPrice;
use App\Modules\Property\Support\RoomPricing;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Stops a room's own price from a date, so the room type price applies again.
 */
final class EndRoomPriceOverride extends Action
{
    public function handle(Room $room, RentalPeriod $period, string $from): RoomPrice
    {
        $this->authorize('managePrices', $room);

        $date = CarbonImmutable::parse($from)->startOfDay();

        return $this->transaction(function () use ($room, $period, $date): RoomPrice {
            $override = RoomPricing::inForce(RoomPrice::query()->where('room_id', $room->id)->lockForUpdate(), $period, $date);

            if ($override === null || $override->effective_from->greaterThanOrEqualTo($date)) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Tidak ada harga khusus kamar yang berlaku sebelum tanggal ini.',
                ]);
            }

            $override->update(['effective_until' => $date->subDay()]);

            return $override;
        });
    }
}
