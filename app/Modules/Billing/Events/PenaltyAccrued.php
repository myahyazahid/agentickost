<?php

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\PenaltyAccrual;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A late penalty was charged for one day.
 */
final class PenaltyAccrued
{
    use Dispatchable;

    public function __construct(public readonly PenaltyAccrual $penalty) {}
}
