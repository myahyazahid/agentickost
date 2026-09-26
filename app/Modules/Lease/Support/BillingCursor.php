<?php

namespace App\Modules\Lease\Support;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\Notice;
use App\Modules\Lease\States\Contract\Terminated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * The contract's billing position: which period to bill next, and the last
 * day it can be billed for. Billing moves it through here instead of
 * writing the contracts table itself.
 */
final class BillingCursor
{
    public function nextPeriodStart(Contract $contract): CarbonImmutable
    {
        return CarbonImmutable::parse($contract->next_period_start);
    }

    public function moveTo(Contract $contract, CarbonImmutable $nextPeriodStart): void
    {
        $contract->next_period_start = Carbon::parse($nextPeriodStart->toDateString());
        $contract->save();
    }

    /**
     * Rent stops at the earliest of the agreed end date, the planned move-out
     * after a notice, and the end date of a terminated contract.
     */
    public function lastBillableDay(Contract $contract): ?CarbonImmutable
    {
        $candidates = array_filter([
            $contract->end_date,
            $contract->status->equals(Notice::class) ? $contract->planned_move_out_on : null,
            $contract->status->equals(Terminated::class) ? $contract->ended_on : null,
        ]);

        if ($candidates === []) {
            return null;
        }

        return CarbonImmutable::parse(min(array_map(fn ($date) => $date->toDateString(), $candidates)));
    }
}
