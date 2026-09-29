<?php

namespace App\Modules\Subscription\Enums;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\DefinesPermissions;

enum SubscriptionPermission: string implements DefinesPermissions
{
    case Manage = 'subscription.manage';
    case ExportData = 'subscription.export-data';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::Manage, self::ExportData => [Role::Owner],
        };
    }
}
