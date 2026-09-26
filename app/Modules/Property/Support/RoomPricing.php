<?php

namespace App\Modules\Property\Support;

use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomPrice;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves the price in force on a date: the room's own override wins over
 * the room type's price (FR-KMR-02, FR-KMR-03).
 */
final class RoomPricing
{
    public function priceFor(Room $room, RentalPeriod $period, CarbonInterface $date): ?int
    {
        return $this->amountInForce(RoomPrice::query()->where('room_id', $room->id), $period, $date)
            ?? $this->amountInForce(RoomPrice::query()->where('room_type_id', $room->room_type_id), $period, $date);
    }

    /**
     * Every period that has a price on the date.
     *
     * @return array<string, int>
     */
    public function pricesFor(Room $room, CarbonInterface $date): array
    {
        $prices = [];

        foreach (RentalPeriod::cases() as $period) {
            $amount = $this->priceFor($room, $period, $date);

            if ($amount !== null) {
                $prices[$period->value] = $amount;
            }
        }

        return $prices;
    }

    /**
     * @param  Builder<RoomPrice>  $query
     */
    public static function inForce(Builder $query, RentalPeriod $period, CarbonInterface $date): ?RoomPrice
    {
        return $query
            ->where('rental_period', $period->value)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $range) => $range
                ->whereNull('effective_until')
                ->orWhereDate('effective_until', '>=', $date))
            ->latest('effective_from')
            ->first();
    }

    /**
     * @param  Builder<RoomPrice>  $query
     */
    private function amountInForce(Builder $query, RentalPeriod $period, CarbonInterface $date): ?int
    {
        return self::inForce($query, $period, $date)?->amount;
    }
}
