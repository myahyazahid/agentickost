<?php

namespace App\Modules\Finance\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Property\Enums\PropertyPermission;

final class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PropertyPermission::View->value);
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(FinancePermission::ManageBankAccounts->value);
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $user->can(FinancePermission::ManageBankAccounts->value);
    }
}
