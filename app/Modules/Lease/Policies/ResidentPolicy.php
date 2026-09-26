<?php

namespace App\Modules\Lease\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Enums\LeasePermission;
use App\Modules\Lease\Models\Resident;

final class ResidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(LeasePermission::ViewResidents->value);
    }

    public function view(User $user, Resident $resident): bool
    {
        return $user->can(LeasePermission::ViewResidents->value) && $resident->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return $user->can(LeasePermission::ManageResidents->value);
    }

    public function update(User $user, Resident $resident): bool
    {
        return $user->can(LeasePermission::ManageResidents->value) && $resident->isAccessibleBy($user);
    }

    public function viewIdentity(User $user, Resident $resident): bool
    {
        return $user->can(LeasePermission::ViewIdentity->value) && $resident->isAccessibleBy($user);
    }

    public function flag(User $user, Resident $resident): bool
    {
        return $user->can(LeasePermission::FlagResidents->value) && $resident->isAccessibleBy($user);
    }
}
