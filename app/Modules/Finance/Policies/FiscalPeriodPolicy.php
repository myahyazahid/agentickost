<?php

namespace App\Modules\Finance\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Finance\Models\FiscalPeriod;

/**
 * Closing the books (FR-ACC-08, PRD §8.11): accountants and owners close a
 * month; only the owner opens it again.
 */
final class FiscalPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(FinancePermission::View->value);
    }

    public function close(User $user): bool
    {
        return $user->can(FinancePermission::ClosePeriods->value);
    }

    public function reopen(User $user, FiscalPeriod $period): bool
    {
        return $user->can(FinancePermission::ReopenPeriods->value);
    }
}
