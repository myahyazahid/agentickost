<?php

namespace App\Modules\Property\Actions;

use App\Modules\Property\Models\RoomType;
use App\Support\Actions\Action;

final class UpdateRoomType extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(RoomType $roomType, array $input): RoomType
    {
        $this->authorize('update', $roomType);

        $data = $this->validate($input, CreateRoomType::rules());

        return $this->transaction(function () use ($roomType, $data): RoomType {
            $roomType->update($data);

            return $roomType;
        });
    }
}
