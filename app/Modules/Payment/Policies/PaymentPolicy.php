<?php

namespace App\Modules\Payment\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Payment\Enums\PaymentPermission;
use App\Modules\Payment\Models\Payment;
use App\Modules\Property\Models\Property;

final class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PaymentPermission::ViewPayments->value);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can(PaymentPermission::ViewPayments->value) && $payment->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PaymentPermission::RecordPayments->value);
    }

    public function recordFor(User $user, Property $property): bool
    {
        return $user->can(PaymentPermission::RecordPayments->value) && $property->isAccessibleBy($user);
    }

    public function verifyIn(User $user, Property $property): bool
    {
        return $user->can(PaymentPermission::VerifyPayments->value) && $property->isAccessibleBy($user);
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $user->can(PaymentPermission::VerifyPayments->value) && $payment->isAccessibleBy($user);
    }

    public function reverse(User $user, Payment $payment): bool
    {
        return $user->can(PaymentPermission::ReversePayments->value) && $payment->isAccessibleBy($user);
    }

    public function applyCreditIn(User $user, Property $property): bool
    {
        return $user->can(PaymentPermission::ApplyCredit->value) && $property->isAccessibleBy($user);
    }
}
