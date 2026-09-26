<?php

namespace App\Modules\Lease\Enums;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\DefinesPermissions;

enum LeasePermission: string implements DefinesPermissions
{
    case ViewResidents = 'resident.view';
    case ManageResidents = 'resident.manage';
    case ViewIdentity = 'resident.view-identity';
    case FlagResidents = 'resident.flag';
    case ViewContracts = 'contract.view';
    case ManageContracts = 'contract.manage';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::ViewResidents, self::ViewContracts => Role::staff(),
            self::ManageResidents => [Role::Owner, Role::Manager, Role::Caretaker],
            self::ViewIdentity, self::FlagResidents, self::ManageContracts => [Role::Owner, Role::Manager],
        };
    }
}
