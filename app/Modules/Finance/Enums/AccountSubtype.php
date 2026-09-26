<?php

namespace App\Modules\Finance\Enums;

/**
 * Roles an account plays in automatic journals (PRD §8.14). System accounts
 * are looked up by subtype, never by code.
 */
enum AccountSubtype: string
{
    case Cash = 'cash';
    case StaffCash = 'staff_cash';
    case Bank = 'bank';
    case Receivable = 'receivable';
    case DepositLiability = 'deposit_liability';
    case CreditLiability = 'credit_liability';
    case AdvanceLiability = 'advance_liability';
    case OpeningEquity = 'opening_equity';
    case RentRevenue = 'rent_revenue';
    case UtilityRevenue = 'utility_revenue';
    case PenaltyRevenue = 'penalty_revenue';
    case AddonRevenue = 'addon_revenue';
    case OtherRevenue = 'other_revenue';
    case UtilityExpense = 'utility_expense';
    case MaintenanceExpense = 'maintenance_expense';
    case OtherExpense = 'other_expense';
}
