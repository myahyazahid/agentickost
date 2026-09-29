<?php

namespace App\Modules\Portal\Enums;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\DefinesPermissions;

enum PortalPermission: string implements DefinesPermissions
{
    case ManageAnnouncements = 'announcement.manage';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::ManageAnnouncements => [Role::Owner, Role::Manager],
        };
    }
}
