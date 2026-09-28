<?php

namespace App\Modules\Finance\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Finance\Models\Expense;
use App\Modules\Property\Models\Property;

/**
 * Staff record what they spend for their properties; only the owner voids
 * an expense (PRD §8.10).
 */
final class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(FinancePermission::RecordExpenses->value) || $user->can(FinancePermission::View->value);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->viewAny($user) && $expense->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return $user->can(FinancePermission::RecordExpenses->value);
    }

    public function recordIn(User $user, Property $property): bool
    {
        return $user->can(FinancePermission::RecordExpenses->value) && $property->isAccessibleBy($user);
    }

    public function void(User $user, Expense $expense): bool
    {
        return $user->can(FinancePermission::VoidExpenses->value) && $expense->isAccessibleBy($user);
    }
}
