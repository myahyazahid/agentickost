<?php

namespace App\Modules\Payment\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Payment\Enums\PaymentPermission;
use App\Modules\Payment\Models\StaffCashHandover;
use App\Modules\Property\Models\Property;

/**
 * Staff record their own handovers; the owner records one for anyone and
 * confirms them (FR-PAY-08).
 */
final class StaffCashHandoverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PaymentPermission::HandOverCash->value) || $user->can(PaymentPermission::ConfirmHandovers->value);
    }

    public function view(User $user, StaffCashHandover $handover): bool
    {
        return $this->confirm($user, $handover)
            || ($handover->staff_user_id === $user->id && $user->can(PaymentPermission::HandOverCash->value));
    }

    public function create(User $user): bool
    {
        return $user->can(PaymentPermission::HandOverCash->value);
    }

    public function handOverIn(User $user, Property $property, User $staff): bool
    {
        if (! $property->isAccessibleBy($user)) {
            return false;
        }

        return $user->can(PaymentPermission::ConfirmHandovers->value)
            || ($staff->id === $user->id && $user->can(PaymentPermission::HandOverCash->value));
    }

    public function confirm(User $user, StaffCashHandover $handover): bool
    {
        return $user->can(PaymentPermission::ConfirmHandovers->value) && $handover->isAccessibleBy($user);
    }
}
