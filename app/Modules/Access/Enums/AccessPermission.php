<?php

namespace App\Modules\Access\Enums;

use App\Modules\Access\Permissions\DefinesPermissions;

enum AccessPermission: string implements DefinesPermissions
{
    case ManageUsers = 'user.manage';
    case ViewAuditLog = 'audit.view';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::ManageUsers => [Role::Owner],
            self::ViewAuditLog => [Role::Owner, Role::Accountant],
        };
    }
}
