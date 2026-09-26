<?php

namespace App\Modules\Finance\Enums;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\DefinesPermissions;

enum FinancePermission: string implements DefinesPermissions
{
    case View = 'finance.view';
    case ManageAccounts = 'finance.manage-accounts';
    case ManageBankAccounts = 'finance.manage-bank-accounts';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::View, self::ManageAccounts => [Role::Owner, Role::Accountant],
            self::ManageBankAccounts => [Role::Owner],
        };
    }
}
