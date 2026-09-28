<?php

namespace App\Modules\Payment\Support;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\Expense;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\StaffCashHandover;
use App\Modules\Payment\States\Payment\Verified;

/**
 * Cash a staff member holds for a property (FR-PAY-07, PRD §8.12): verified
 * cash payments they received, less what they handed over and what they
 * spent from it. The owner's own
 * cash goes straight to the tenant's cash account, so owners hold none.
 */
final class StaffCash
{
    public static function tracks(User $user): bool
    {
        return ! $user->hasRole(Role::Owner->value);
    }

    public static function balance(string $userId, string $propertyId): int
    {
        return self::received($userId, $propertyId) - self::handedOver($userId, $propertyId) - self::spent($userId, $propertyId);
    }

    /**
     * Expenses the staff member paid from the cash they hold (FR-ACC-03).
     */
    public static function spent(string $userId, string $propertyId): int
    {
        return (int) Expense::query()
            ->where('property_id', $propertyId)
            ->whereNull('voided_at')
            ->whereIn('paid_from_account_id', Account::query()->where('user_id', $userId)->select('id'))
            ->sum('amount');
    }

    public static function received(string $userId, string $propertyId): int
    {
        return (int) Payment::query()
            ->where('received_by_user_id', $userId)
            ->where('property_id', $propertyId)
            ->where('method', PaymentMethod::Cash->value)
            ->where('status', Verified::$name)
            ->sum('amount');
    }

    /**
     * Every recorded handover counts at the amount expected then; a
     * shortfall is settled on the handover itself, not carried over.
     */
    public static function handedOver(string $userId, string $propertyId): int
    {
        return (int) StaffCashHandover::query()
            ->where('staff_user_id', $userId)
            ->where('property_id', $propertyId)
            ->sum('expected_amount');
    }
}
