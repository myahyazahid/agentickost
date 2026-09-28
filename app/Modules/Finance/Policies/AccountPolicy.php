<?php

namespace App\Modules\Finance\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Finance\Models\Account;

/**
 * The chart of accounts and its ledgers (FR-ACC-01).
 */
final class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(FinancePermission::View->value);
    }

    public function view(User $user, Account $account): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(FinancePermission::ManageAccounts->value);
    }

    public function update(User $user, Account $account): bool
    {
        return $user->can(FinancePermission::ManageAccounts->value);
    }
}
