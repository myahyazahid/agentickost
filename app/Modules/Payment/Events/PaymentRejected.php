<?php

namespace App\Modules\Payment\Events;

use App\Modules\Payment\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A pending payment was rejected with a reason.
 */
final class PaymentRejected
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
