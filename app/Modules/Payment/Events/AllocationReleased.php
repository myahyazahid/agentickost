<?php

namespace App\Modules\Payment\Events;

use App\Modules\Payment\Models\PaymentAllocation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Part of an allocation was taken off its invoice and returned to its
 * source: a payment's money or used credit back to the credit balance, used
 * deposit back to the deposit. The invoice owes that amount again.
 */
final class AllocationReleased
{
    use Dispatchable;

    public function __construct(
        public readonly PaymentAllocation $allocation,
        public readonly int $amount,
    ) {}
}
