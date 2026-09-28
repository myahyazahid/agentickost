<?php

namespace App\Modules\Finance\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Finance\Models\JournalEntry;

/**
 * Journals are only read: they are posted by the system (FR-ACC-02).
 */
final class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(FinancePermission::View->value);
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $this->viewAny($user);
    }
}
