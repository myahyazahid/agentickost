<?php

namespace App\Modules\Tenancy\Policies;

use Illuminate\Contracts\Auth\Authenticatable;

final class TenantPolicy
{
    /**
     * Tenants are created by registration or by the system, never by tenant staff.
     */
    public function create(Authenticatable $user): bool
    {
        return false;
    }
}
