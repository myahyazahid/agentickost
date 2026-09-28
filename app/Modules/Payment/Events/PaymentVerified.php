<?php

namespace App\Modules\Payment\Events;

use App\Modules\Payment\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A payment was verified and allocated. Any overpayment is already on the credit balance.
 */
final class PaymentVerified
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
