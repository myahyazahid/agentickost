<?php

namespace App\Modules\Property\Actions;

use App\Modules\Property\Models\Room;
use App\Modules\Property\States\Room\Available;
use App\Modules\Property\States\Room\Maintenance;
use App\Support\Actions\Action;
use App\Support\States\StateTransition;
use Illuminate\Validation\ValidationException;

/**
 * Takes an available room out of service for repairs (PRD §9.1). Occupied
 * rooms reach maintenance through check-out, not through this action.
 */
final class StartRoomMaintenance extends Action
{
    public function handle(Room $room): Room
    {
        $this->authorize('manageStatus', $room);

        if (! $room->status->equals(Available::class)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya kamar berstatus Tersedia yang bisa masuk perbaikan.',
            ]);
        }

        $this->transaction(fn () => StateTransition::to($room->status, Maintenance::class));

        return $room;
    }
}
