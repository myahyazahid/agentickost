<?php

namespace App\Modules\Property\Support;

use App\Modules\Property\Models\Room;
use App\Modules\Property\States\Room\Available;
use App\Modules\Property\States\Room\Maintenance;
use App\Modules\Property\States\Room\Occupied;
use App\Modules\Property\States\Room\Vacating;
use App\Support\States\StateTransition;
use Illuminate\Validation\ValidationException;

/**
 * Room status changes driven by contracts. Other modules call this instead
 * of writing rooms.status; the caller's Action has already authorized.
 */
final class RoomOccupancy
{
    /**
     * Lock the room row for the rest of the transaction, so two contracts
     * cannot take the same room at once (schema §14.2).
     */
    public function lock(Room $room): Room
    {
        return Room::query()->lockForUpdate()->whereKey($room->id)->firstOrFail();
    }

    public function occupy(Room $room): void
    {
        if ($room->status->equals(Occupied::class)) {
            throw ValidationException::withMessages(['room_id' => "Kamar {$room->number} sudah terisi."]);
        }

        StateTransition::to($room->status, Occupied::class, 'room_id');
    }

    public function markVacating(Room $room): void
    {
        if ($room->status->equals(Occupied::class)) {
            StateTransition::to($room->status, Vacating::class, 'room_id');
        }
    }

    public function cancelVacating(Room $room): void
    {
        if ($room->status->equals(Vacating::class)) {
            StateTransition::to($room->status, Occupied::class, 'room_id');
        }
    }

    /**
     * After check-out: the room needs repairs or is ready to rent again.
     */
    public function release(Room $room, bool $needsMaintenance): void
    {
        StateTransition::to($room->status, $needsMaintenance ? Maintenance::class : Available::class, 'room_id');
    }
}
