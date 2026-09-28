<?php

namespace App\Modules\Payment\Events;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A verified payment was reversed. Carries what it still paid at that
 * moment: the allocations undone and the credit balance taken back, which
 * together equal the payment amount.
 */
final class PaymentReversed
{
    use Dispatchable;

    /**
     * @param  list<PaymentAllocation>  $allocations
     */
    public function __construct(
        public readonly Payment $payment,
        public readonly array $allocations,
        public readonly int $creditTakenBack,
    ) {}
}
