<?php

namespace App\Modules\Payment\Events;

use App\Modules\Payment\Models\StaffCashHandover;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The owner confirmed a staff cash handover.
 */
final class CashHandoverConfirmed
{
    use Dispatchable;

    public function __construct(public readonly StaffCashHandover $handover) {}
}
