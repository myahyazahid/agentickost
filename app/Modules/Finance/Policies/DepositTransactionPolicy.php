<?php

namespace App\Modules\Finance\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Lease\Models\Contract;

/**
 * Deposits are owed to residents, so only the owner takes from them
 * (PRD §8.6).
 */
final class DepositTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(FinancePermission::ViewDeposits->value);
    }

    public function manageFor(User $user, Contract $contract): bool
    {
        return $user->can(FinancePermission::ManageDeposits->value) && $contract->isAccessibleBy($user);
    }
}
