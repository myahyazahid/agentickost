<?php

namespace App\Modules\Tenancy\Policies;

use App\Modules\Tenancy\Models\PlatformAdmin;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Tenants are managed by super admins only (FR-TNT-04 to FR-TNT-06).
 */
final class TenantPolicy
{
    /**
     * Tenants are created by registration or by the system, never by tenant staff.
     */
    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function viewAny(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin;
    }

    public function view(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin;
    }

    public function freeze(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin;
    }

    public function manageTrial(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin;
    }

    public function impersonate(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin;
    }

    public function manageSettings(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin;
    }
}
