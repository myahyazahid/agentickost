<?php

namespace App\Modules\Tenancy\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Tenancy\Enums\TenancyPermission;
use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Tenants are managed by super admins (FR-TNT-04 to FR-TNT-06); the owner
 * only edits the business profile (FR-SUB-07).
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

    public function updateProfile(Authenticatable $user, Tenant $tenant): bool
    {
        return $user instanceof User
            && $user->tenant_id === $tenant->id
            && $user->can(TenancyPermission::ManageProfile->value);
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
