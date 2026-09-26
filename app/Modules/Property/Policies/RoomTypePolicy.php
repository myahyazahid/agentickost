<?php

namespace App\Modules\Property\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Property\Enums\PropertyPermission;
use App\Modules\Property\Models\RoomType;

final class RoomTypePolicy
{
    public function __construct(private readonly PropertyPolicy $properties) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PropertyPermission::View->value);
    }

    public function view(User $user, RoomType $roomType): bool
    {
        return $this->properties->view($user, $roomType->property()->firstOrFail());
    }

    public function create(User $user): bool
    {
        return $user->can(PropertyPermission::ManageRooms->value);
    }

    public function update(User $user, RoomType $roomType): bool
    {
        return $this->properties->manageRooms($user, $roomType->property()->firstOrFail());
    }

    public function delete(User $user, RoomType $roomType): bool
    {
        return $this->update($user, $roomType);
    }

    public function managePrices(User $user, RoomType $roomType): bool
    {
        return $this->properties->managePrices($user, $roomType->property()->firstOrFail());
    }
}
