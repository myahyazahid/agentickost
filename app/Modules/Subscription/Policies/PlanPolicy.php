<?php

namespace App\Modules\Subscription\Policies;

use App\Modules\Tenancy\Models\PlatformAdmin;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Plans are set up by super admins only.
 */
final class PlanPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin;
    }

    public function create(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin;
    }

    public function update(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin;
    }
}
