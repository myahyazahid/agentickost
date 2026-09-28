<?php

namespace App\Modules\Onboarding\Enums;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\DefinesPermissions;

enum OnboardingPermission: string implements DefinesPermissions
{
    case Manage = 'onboarding.manage';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::Manage => [Role::Owner],
        };
    }
}
