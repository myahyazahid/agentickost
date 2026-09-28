<?php

namespace App\Modules\Finance\Enums;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\DefinesPermissions;

enum FinancePermission: string implements DefinesPermissions
{
    case View = 'finance.view';
    case ManageAccounts = 'finance.manage-accounts';
    case ManageBankAccounts = 'finance.manage-bank-accounts';
    case ViewDeposits = 'deposit.view';
    case ManageDeposits = 'deposit.manage';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::View, self::ManageAccounts => [Role::Owner, Role::Accountant],
            self::ViewDeposits => [Role::Owner, Role::Manager, Role::Accountant],
            self::ManageBankAccounts, self::ManageDeposits => [Role::Owner],
        };
    }
}
