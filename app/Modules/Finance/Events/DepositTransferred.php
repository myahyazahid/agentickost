<?php

namespace App\Modules\Finance\Events;

use App\Modules\Finance\Models\DepositTransaction;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A deposit moved to another contract. The event carries the outgoing entry.
 */
final class DepositTransferred
{
    use Dispatchable;

    public function __construct(public readonly DepositTransaction $entry) {}
}
