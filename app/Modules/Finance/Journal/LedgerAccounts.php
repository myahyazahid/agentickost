<?php

namespace App\Modules\Finance\Journal;

use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Models\Account;
use App\Modules\Property\Enums\AllocationCategory;

/**
 * Which system account an automatic journal uses (PRD §8.14). Accounts are
 * looked up by subtype, never by code.
 */
final class LedgerAccounts
{
    public static function id(AccountSubtype $subtype): string
    {
        return Account::system($subtype)->id;
    }

    /**
     * The income account of an invoice component. The deposit component is
     * a liability: it is booked when received, not when billed.
     */
    public static function revenueFor(AllocationCategory $category): string
    {
        return self::id(match ($category) {
            AllocationCategory::Deposit => AccountSubtype::DepositLiability,
            AllocationCategory::Rent => AccountSubtype::RentRevenue,
            AllocationCategory::Utility => AccountSubtype::UtilityRevenue,
            AllocationCategory::Addon => AccountSubtype::AddonRevenue,
            AllocationCategory::Other => AccountSubtype::OtherRevenue,
            AllocationCategory::Penalty => AccountSubtype::PenaltyRevenue,
        });
    }

    /**
     * What money allocated to an invoice component settles: the receivable,
     * or for the deposit component the deposit owed to the resident.
     */
    public static function settledBy(AllocationCategory $category): string
    {
        return self::id($category === AllocationCategory::Deposit ? AccountSubtype::DepositLiability : AccountSubtype::Receivable);
    }
}
