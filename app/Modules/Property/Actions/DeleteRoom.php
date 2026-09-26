<?php

namespace App\Modules\Property\Actions;

use App\Modules\Property\Models\Room;
use App\Modules\Property\States\Room\Available;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a room. Only an available room can go; its history stays.
 */
final class DeleteRoom extends Action
{
    public function handle(Room $room): void
    {
        $this->authorize('delete', $room);

        if (! $room->status->equals(Available::class)) {
            throw ValidationException::withMessages([
                'room' => 'Hanya kamar berstatus Tersedia yang bisa dihapus.',
            ]);
        }

        $this->transaction(fn () => $room->delete());
    }
}
