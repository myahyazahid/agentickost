<?php

namespace App\Modules\Finance\Support;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Lease\Models\Contract;
use App\Modules\Property\Models\Property;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deposit held per property (FR-DEP-04): the sum of its contracts' deposit
 * ledgers, and how many contracts still have deposit.
 */
final class DepositsHeld
{
    /**
     * Properties the user may see, with `deposit_held_amount` and
     * `contracts_holding_count` selected.
     *
     * @return Builder<Property>
     */
    public static function perProperty(User $user): Builder
    {
        $contractsHolding = DepositTransaction::query()
            ->select('contract_id')
            ->groupBy('contract_id')
            ->havingRaw('SUM(amount) > 0');

        return Property::query()
            ->accessibleBy($user)
            ->select('properties.*')
            ->selectSub(
                DepositTransaction::query()
                    ->join('contracts', 'contracts.id', '=', 'deposit_transactions.contract_id')
                    ->whereColumn('contracts.property_id', 'properties.id')
                    ->selectRaw('COALESCE(SUM(deposit_transactions.amount), 0)'),
                'deposit_held_amount',
            )
            ->selectSub(
                Contract::query()
                    ->whereColumn('contracts.property_id', 'properties.id')
                    ->whereIn('contracts.id', $contractsHolding)
                    ->selectRaw('COUNT(*)'),
                'contracts_holding_count',
            );
    }
}
