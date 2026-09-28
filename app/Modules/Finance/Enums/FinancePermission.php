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
    case RecordExpenses = 'expense.record';
    case SpendFromCashAndBank = 'expense.spend-cash-bank';
    case VoidExpenses = 'expense.void';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::View, self::ManageAccounts => [Role::Owner, Role::Accountant],
            self::ViewDeposits => [Role::Owner, Role::Manager, Role::Accountant],
            self::ManageBankAccounts, self::ManageDeposits, self::VoidExpenses => [Role::Owner],
            self::RecordExpenses => [Role::Owner, Role::Manager, Role::Caretaker],
            self::SpendFromCashAndBank => [Role::Owner, Role::Manager],
        };
    }
}
