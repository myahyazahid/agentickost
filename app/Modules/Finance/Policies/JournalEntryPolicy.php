<?php

namespace App\Modules\Finance\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Finance\Models\JournalEntry;

/**
 * Journals are posted by the system (FR-ACC-02). Accountants may add a
 * manual journal and reverse it; a journal is never edited (FR-ACC-05).
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

    public function createManual(User $user): bool
    {
        return $user->can(FinancePermission::PostManualJournals->value);
    }

    public function reverseManual(User $user, JournalEntry $entry): bool
    {
        return $user->can(FinancePermission::PostManualJournals->value);
    }
}
