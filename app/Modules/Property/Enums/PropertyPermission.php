<?php

namespace App\Modules\Property\Enums;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\DefinesPermissions;

enum PropertyPermission: string implements DefinesPermissions
{
    case View = 'property.view';
    case Create = 'property.create';
    case Update = 'property.update';
    case Delete = 'property.delete';
    case AssignStaff = 'property.assign-staff';
    case ManageSettings = 'property.manage-settings';
    case ManageRooms = 'room.manage';
    case ManagePrices = 'room.manage-prices';
    case ManageRoomStatus = 'room.manage-status';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::View => Role::staff(),
            self::Update, self::ManageRooms => [Role::Owner, Role::Manager],
            self::ManageRoomStatus => [Role::Owner, Role::Manager, Role::Caretaker],
            self::Create, self::Delete, self::AssignStaff, self::ManageSettings, self::ManagePrices => [Role::Owner],
        };
    }
}
