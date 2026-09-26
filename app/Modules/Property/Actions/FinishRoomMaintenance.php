<?php

namespace App\Modules\Property\Actions;

use App\Modules\Property\Models\Room;
use App\Modules\Property\States\Room\Available;
use App\Modules\Property\States\Room\Maintenance;
use App\Support\Actions\Action;
use App\Support\States\StateTransition;
use Illuminate\Validation\ValidationException;

/**
 * Puts a repaired room back on offer (PRD §9.1).
 */
final class FinishRoomMaintenance extends Action
{
    public function handle(Room $room): Room
    {
        $this->authorize('manageStatus', $room);

        if (! $room->status->equals(Maintenance::class)) {
            throw ValidationException::withMessages([
                'status' => 'Kamar ini tidak sedang dalam perbaikan.',
            ]);
        }

        $this->transaction(fn () => StateTransition::to($room->status, Available::class));

        return $room;
    }
}
