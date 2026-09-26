<?php

namespace App\Modules\Documents\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\DocumentPermission;

final class DocumentSequencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(DocumentPermission::ManageNumbering->value);
    }

    public function update(User $user): bool
    {
        return $user->can(DocumentPermission::ManageNumbering->value);
    }
}
