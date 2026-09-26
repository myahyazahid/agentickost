<?php

namespace App\Modules\Access\Permissions;

use App\Modules\Access\Enums\Role;

/**
 * Implemented by a module's string-backed permission enum. Each case value is
 * a permission name such as `property.create`.
 */
interface DefinesPermissions
{
    /**
     * Roles that receive this permission when a tenant's roles are synced.
     *
     * @return list<Role>
     */
    public function defaultRoles(): array;
}
