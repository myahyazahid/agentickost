<?php

namespace App\Modules\Payment\Events;

use App\Modules\Payment\Models\CreditTransaction;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Credit balance was used to pay an invoice.
 */
final class CreditApplied
{
    use Dispatchable;

    public function __construct(public readonly CreditTransaction $entry) {}
}
