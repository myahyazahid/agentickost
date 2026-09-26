<?php

namespace App\Modules\Access\Policies;

use App\Modules\Access\Enums\AccessPermission;
use App\Modules\Access\Models\User;

final class UserPolicy
{
    public function create(User $user): bool
    {
        return $user->can(AccessPermission::ManageUsers->value);
    }
}
