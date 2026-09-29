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
    case ManageOpeningBalances = 'finance.opening-balance';
    case PostManualJournals = 'journal.manual';
    case ClosePeriods = 'finance.close-period';
    case ReopenPeriods = 'finance.reopen-period';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::View, self::ManageAccounts, self::PostManualJournals, self::ClosePeriods => [Role::Owner, Role::Accountant],
            self::ViewDeposits => [Role::Owner, Role::Manager, Role::Accountant],
            self::ManageBankAccounts, self::ManageDeposits, self::VoidExpenses, self::ManageOpeningBalances, self::ReopenPeriods => [Role::Owner],
            self::RecordExpenses => [Role::Owner, Role::Manager, Role::Caretaker],
            self::SpendFromCashAndBank => [Role::Owner, Role::Manager],
        };
    }
}
