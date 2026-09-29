<?php

namespace App\Modules\Payment\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Payer;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\Support\PortalAccess;
use App\Modules\Payment\Enums\PaymentPermission;
use App\Modules\Payment\Models\Payment;
use App\Modules\Property\Models\Property;
use Illuminate\Contracts\Auth\Authenticatable;

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

    /**
     * A resident or payer sends proof of a transfer for a contract they can
     * see in the portal (FR-PRT-03).
     */
    public function submitProof(Authenticatable $account, Contract $contract): bool
    {
        return ($account instanceof Resident || $account instanceof Payer) && PortalAccess::for($account)->canSee($contract);
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
