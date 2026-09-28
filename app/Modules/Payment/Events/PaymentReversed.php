<?php

namespace App\Modules\Payment\Events;

use App\Modules\Payment\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A verified payment was reversed; its allocations and credit are undone.
 */
final class PaymentReversed
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
