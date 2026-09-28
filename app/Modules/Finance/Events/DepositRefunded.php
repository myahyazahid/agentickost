<?php

namespace App\Modules\Finance\Events;

use App\Modules\Finance\Models\DepositTransaction;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Part of a deposit was paid back to the resident.
 */
final class DepositRefunded
{
    use Dispatchable;

    public function __construct(public readonly DepositTransaction $entry) {}
}
