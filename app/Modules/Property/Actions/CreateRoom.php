<?php

namespace App\Modules\Property\Actions;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomType;
use App\Support\Actions\Action;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

final class CreateRoom extends Action
{
    public function __construct(private readonly AttachmentSync $attachments) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Property $property, array $input): Room
    {
        $this->authorize('manageRooms', $property);

        $data = $this->validate($input, self::rules($property));

        return $this->transaction(function () use ($property, $data): Room {
            $roomType = RoomType::query()->whereKey($data['room_type_id'])->firstOrFail();
            $data['capacity'] ??= $roomType->default_capacity;

            $room = $property->rooms()->create(Arr::except($data, 'photos'));

            $this->attachments->sync($room, AttachmentCollection::Photo, $data['photos'] ?? []);

            return $room;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(Property $property, ?Room $ignore = null): array
    {
        return [
            'room_type_id' => [
                'required',
                Rule::exists('room_types', 'id')->where('property_id', $property->id)->whereNull('deleted_at'),
            ],
            'number' => [
                'required', 'string', 'max:20',
                Rule::unique('rooms', 'number')->where('property_id', $property->id)->ignore($ignore?->id),
            ],
            'floor' => ['nullable', 'string', 'max:10'],
            'capacity' => ['nullable', 'integer', 'between:1,20'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string', 'max:80'],
            'notes' => ['nullable', 'string'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['string'],
        ];
    }
}
