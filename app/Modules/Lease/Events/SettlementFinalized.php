<?php

namespace App\Modules\Lease\Events;

use App\Modules\Lease\Models\Settlement;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A check-out was settled and the contract ended (FR-SIK-05).
 */
final class SettlementFinalized
{
    use Dispatchable;

    public function __construct(public readonly Settlement $settlement) {}
}
