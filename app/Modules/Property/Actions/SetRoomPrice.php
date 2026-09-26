<?php

namespace App\Modules\Property\Actions;

use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomPrice;
use App\Modules\Property\Models\RoomType;
use App\Modules\Property\Support\RoomPricing;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sets the price of a room type, or a room override, from a date onward
 * (FR-KMR-01 to FR-KMR-03). The price in force before that date is closed the
 * day before; history is never edited. Running contracts keep their own
 * locked price and are not affected.
 */
final class SetRoomPrice extends Action
{
    public function __construct(private readonly ActorContext $actors) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(RoomType|Room $target, array $input): RoomPrice
    {
        $this->authorize('managePrices', $target);

        $data = $this->validate($input, [
            'rental_period' => ['required', Rule::enum(RentalPeriod::class)],
            'amount' => ['required', 'integer', 'min:0'],
            'effective_from' => ['required', 'date'],
        ]);

        $period = RentalPeriod::from($data['rental_period']);
        $from = CarbonImmutable::parse($data['effective_from'])->startOfDay();

        return $this->transaction(function () use ($target, $period, $from, $data): RoomPrice {
            $history = fn (): Builder => RoomPrice::query()
                ->where($target instanceof Room ? 'room_id' : 'room_type_id', $target->id)
                ->lockForUpdate();

            if ($history()->where('rental_period', $period->value)->whereDate('effective_from', '>=', $from)->exists()) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Sudah ada harga yang berlaku mulai tanggal ini atau sesudahnya. Pilih tanggal yang lebih akhir.',
                ]);
            }

            RoomPricing::inForce($history(), $period, $from)?->update(['effective_until' => $from->subDay()]);

            $actor = $this->actors->current();

            return RoomPrice::create([
                'room_type_id' => $target instanceof RoomType ? $target->id : null,
                'room_id' => $target instanceof Room ? $target->id : null,
                'rental_period' => $period,
                'amount' => $data['amount'],
                'effective_from' => $from,
                'created_by' => $actor->type === ActorType::User ? $actor->id : null,
            ]);
        });
    }
}
