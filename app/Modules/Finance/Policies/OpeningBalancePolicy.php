<?php

namespace App\Modules\Finance\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Finance\Models\OpeningBalance;

/**
 * The owner enters and posts opening balances; the accountant can check
 * them against the old books.
 */
final class OpeningBalancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(FinancePermission::ManageOpeningBalances->value) || $user->can(FinancePermission::View->value);
    }

    public function view(User $user, OpeningBalance $openingBalance): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(FinancePermission::ManageOpeningBalances->value);
    }

    public function update(User $user, OpeningBalance $openingBalance): bool
    {
        return $this->create($user) && $openingBalance->isDraft();
    }

    public function delete(User $user, OpeningBalance $openingBalance): bool
    {
        return $this->update($user, $openingBalance);
    }

    public function post(User $user, OpeningBalance $openingBalance): bool
    {
        return $this->update($user, $openingBalance);
    }
}
