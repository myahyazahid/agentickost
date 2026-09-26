<?php

namespace App\Modules\Property\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Property\Enums\PropertyPermission;
use App\Modules\Property\Models\Room;

final class RoomPolicy
{
    public function __construct(private readonly PropertyPolicy $properties) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PropertyPermission::View->value);
    }

    public function view(User $user, Room $room): bool
    {
        return $this->properties->view($user, $room->property()->firstOrFail());
    }

    public function create(User $user): bool
    {
        return $user->can(PropertyPermission::ManageRooms->value);
    }

    public function update(User $user, Room $room): bool
    {
        return $this->properties->manageRooms($user, $room->property()->firstOrFail());
    }

    public function delete(User $user, Room $room): bool
    {
        return $this->update($user, $room);
    }

    public function managePrices(User $user, Room $room): bool
    {
        return $this->properties->managePrices($user, $room->property()->firstOrFail());
    }

    public function manageStatus(User $user, Room $room): bool
    {
        return $this->properties->manageRoomStatus($user, $room->property()->firstOrFail());
    }
}
