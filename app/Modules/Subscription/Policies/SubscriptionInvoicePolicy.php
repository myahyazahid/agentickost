<?php

namespace App\Modules\Subscription\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Subscription\Enums\SubscriptionPermission;
use App\Modules\Tenancy\Models\PlatformAdmin;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Only super admins confirm or void subscription payments until the payment
 * gateway takes over confirming them (FR-SUB-03).
 */
final class SubscriptionInvoicePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin || ($user instanceof User && $user->can(SubscriptionPermission::Manage->value));
    }

    public function markPaid(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin;
    }

    public function void(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin;
    }
}
