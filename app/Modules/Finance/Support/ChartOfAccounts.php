<?php

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\AccountType;

/**
 * Built-in accounts every tenant starts with. Documented in
 * docs/chart-of-accounts.md.
 */
final class ChartOfAccounts
{
    /**
     * @return list<array{code: string, name: string, type: AccountType, subtype: AccountSubtype|null}>
     */
    public static function defaults(): array
    {
        return [
            ['code' => '1-1000', 'name' => 'Kas', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::Cash],
            ['code' => '1-1100', 'name' => 'Kas di tangan staf', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::StaffCash],
            ['code' => '1-1200', 'name' => 'Bank dan e-wallet', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::Bank],
            ['code' => '1-1300', 'name' => 'Piutang penghuni', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::Receivable],
            ['code' => '2-1000', 'name' => 'Utang deposit penghuni', 'type' => AccountType::Liability, 'subtype' => AccountSubtype::DepositLiability],
            ['code' => '2-1100', 'name' => 'Saldo kredit penghuni', 'type' => AccountType::Liability, 'subtype' => AccountSubtype::CreditLiability],
            ['code' => '2-1200', 'name' => 'Uang muka penghuni', 'type' => AccountType::Liability, 'subtype' => AccountSubtype::AdvanceLiability],
            ['code' => '3-1000', 'name' => 'Ekuitas saldo awal', 'type' => AccountType::Equity, 'subtype' => AccountSubtype::OpeningEquity],
            ['code' => '4-1000', 'name' => 'Pendapatan sewa', 'type' => AccountType::Revenue, 'subtype' => AccountSubtype::RentRevenue],
            ['code' => '4-1100', 'name' => 'Pendapatan utilitas', 'type' => AccountType::Revenue, 'subtype' => AccountSubtype::UtilityRevenue],
            ['code' => '4-1200', 'name' => 'Pendapatan denda', 'type' => AccountType::Revenue, 'subtype' => AccountSubtype::PenaltyRevenue],
            ['code' => '4-1300', 'name' => 'Pendapatan layanan tambahan', 'type' => AccountType::Revenue, 'subtype' => AccountSubtype::AddonRevenue],
            ['code' => '4-1900', 'name' => 'Pendapatan lain-lain', 'type' => AccountType::Revenue, 'subtype' => AccountSubtype::OtherRevenue],
            ['code' => '5-1000', 'name' => 'Beban listrik dan air', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::UtilityExpense],
            ['code' => '5-1100', 'name' => 'Beban perbaikan dan pemeliharaan', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::MaintenanceExpense],
            ['code' => '5-1200', 'name' => 'Beban kebersihan', 'type' => AccountType::Expense, 'subtype' => null],
            ['code' => '5-1300', 'name' => 'Beban gaji dan honor', 'type' => AccountType::Expense, 'subtype' => null],
            ['code' => '5-1400', 'name' => 'Beban internet', 'type' => AccountType::Expense, 'subtype' => null],
            ['code' => '5-1900', 'name' => 'Beban lain-lain', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::OtherExpense],
        ];
    }
}
