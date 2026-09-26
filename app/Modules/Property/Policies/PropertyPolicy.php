<?php

namespace App\Modules\Property\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Property\Enums\PropertyPermission;
use App\Modules\Property\Models\Property;

/**
 * Checks the role permission, then whether the user may see this property.
 */
final class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PropertyPermission::View->value);
    }

    public function view(User $user, Property $property): bool
    {
        return $this->allows($user, PropertyPermission::View, $property);
    }

    public function create(User $user): bool
    {
        return $user->can(PropertyPermission::Create->value);
    }

    public function update(User $user, Property $property): bool
    {
        return $this->allows($user, PropertyPermission::Update, $property);
    }

    public function delete(User $user, Property $property): bool
    {
        return $this->allows($user, PropertyPermission::Delete, $property);
    }

    public function assignStaff(User $user, Property $property): bool
    {
        return $this->allows($user, PropertyPermission::AssignStaff, $property);
    }

    public function manageSettings(User $user, Property $property): bool
    {
        return $this->allows($user, PropertyPermission::ManageSettings, $property);
    }

    public function manageRooms(User $user, Property $property): bool
    {
        return $this->allows($user, PropertyPermission::ManageRooms, $property);
    }

    public function managePrices(User $user, Property $property): bool
    {
        return $this->allows($user, PropertyPermission::ManagePrices, $property);
    }

    public function manageRoomStatus(User $user, Property $property): bool
    {
        return $this->allows($user, PropertyPermission::ManageRoomStatus, $property);
    }

    private function allows(User $user, PropertyPermission $permission, Property $property): bool
    {
        return $user->can($permission->value) && $property->isAccessibleBy($user);
    }
}
