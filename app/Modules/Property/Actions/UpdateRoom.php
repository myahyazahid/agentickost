<?php

namespace App\Modules\Property\Actions;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomType;
use App\Support\Actions\Action;
use Illuminate\Support\Arr;

final class UpdateRoom extends Action
{
    public function __construct(private readonly AttachmentSync $attachments) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Room $room, array $input): Room
    {
        $this->authorize('update', $room);

        $data = $this->validate($input, CreateRoom::rules($room->property()->firstOrFail(), $room));

        return $this->transaction(function () use ($room, $data): Room {
            $data['capacity'] ??= RoomType::query()->whereKey($data['room_type_id'])->firstOrFail()->default_capacity;

            $room->update(Arr::except($data, 'photos'));

            if (array_key_exists('photos', $data)) {
                $this->attachments->sync($room, AttachmentCollection::Photo, $data['photos'] ?? []);
            }

            return $room;
        });
    }
}
