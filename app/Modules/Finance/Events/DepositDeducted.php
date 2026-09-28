<?php

namespace App\Modules\Finance\Events;

use App\Modules\Finance\Models\DepositTransaction;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Part of a deposit was kept, as income or to pay an invoice.
 */
final class DepositDeducted
{
    use Dispatchable;

    public function __construct(public readonly DepositTransaction $entry) {}
}
