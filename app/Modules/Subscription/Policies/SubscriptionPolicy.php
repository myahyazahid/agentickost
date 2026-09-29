<?php

namespace App\Modules\Subscription\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Subscription\Enums\SubscriptionPermission;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Tenancy\Models\PlatformAdmin;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The owner chooses, cancels, and resumes the subscription; super admins
 * see every tenant's.
 */
final class SubscriptionPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin;
    }

    public function view(Authenticatable $user, Subscription $subscription): bool
    {
        return $user instanceof PlatformAdmin || $this->manage($user);
    }

    public function manage(Authenticatable $user): bool
    {
        return $user instanceof User && $user->can(SubscriptionPermission::Manage->value);
    }

    public function exportData(Authenticatable $user): bool
    {
        return $user instanceof User && $user->can(SubscriptionPermission::ExportData->value);
    }

    /**
     * Only the scheduler, acting as the system, moves a subscription along
     * its lifecycle.
     */
    public function advance(Authenticatable $user): bool
    {
        return false;
    }
}
