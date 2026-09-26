<?php

namespace App\Modules\Documents\Enums;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\DefinesPermissions;

enum DocumentPermission: string implements DefinesPermissions
{
    case ManageNumbering = 'document.manage-numbering';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::ManageNumbering => [Role::Owner],
        };
    }
}
