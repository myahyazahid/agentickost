<?php

namespace App\Modules\Tenancy\Enums;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\DefinesPermissions;

enum TenancyPermission: string implements DefinesPermissions
{
    case ManageProfile = 'tenant.manage-profile';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::ManageProfile => [Role::Owner],
        };
    }
}
