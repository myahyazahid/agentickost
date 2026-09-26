<?php

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\PenaltyAccrual;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A late penalty was waived with a reason.
 */
final class PenaltyWaived
{
    use Dispatchable;

    public function __construct(public readonly PenaltyAccrual $penalty) {}
}
