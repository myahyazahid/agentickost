<?php

namespace App\Modules\Maintenance\Enums;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\DefinesPermissions;

enum MaintenancePermission: string implements DefinesPermissions
{
    case ViewTickets = 'ticket.view';
    case ReportTickets = 'ticket.report';
    case ManageTickets = 'ticket.manage';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::ViewTickets => Role::staff(),
            self::ReportTickets => [Role::Owner, Role::Manager, Role::Caretaker],
            self::ManageTickets => [Role::Owner, Role::Manager],
        };
    }
}
