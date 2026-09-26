<?php

namespace App\Modules\Property\Actions;

use App\Modules\Property\Models\RoomType;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

final class DeleteRoomType extends Action
{
    public function handle(RoomType $roomType): void
    {
        $this->authorize('delete', $roomType);

        if ($roomType->rooms()->exists()) {
            throw ValidationException::withMessages([
                'room_type' => 'Tipe ini masih dipakai kamar. Pindahkan atau hapus kamarnya lebih dulu.',
            ]);
        }

        $this->transaction(fn () => $roomType->delete());
    }
}
