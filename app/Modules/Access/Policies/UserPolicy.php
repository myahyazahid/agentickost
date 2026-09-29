<?php

namespace App\Modules\Access\Policies;

use App\Modules\Access\Enums\AccessPermission;
use App\Modules\Access\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(AccessPermission::ManageUsers->value);
    }

    public function create(User $user): bool
    {
        return $user->can(AccessPermission::ManageUsers->value);
    }
}
