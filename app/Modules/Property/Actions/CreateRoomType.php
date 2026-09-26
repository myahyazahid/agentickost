<?php

namespace App\Modules\Property\Actions;

use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\RoomType;
use App\Support\Actions\Action;

final class CreateRoomType extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Property $property, array $input): RoomType
    {
        $this->authorize('manageRooms', $property);

        $data = $this->validate($input, self::rules());

        return $this->transaction(fn (): RoomType => $property->roomTypes()->create($data));
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'default_capacity' => ['required', 'integer', 'between:1,20'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string', 'max:80'],
        ];
    }
}
