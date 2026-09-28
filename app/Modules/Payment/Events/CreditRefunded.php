<?php

namespace App\Modules\Payment\Events;

use App\Modules\Payment\Models\CreditTransaction;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Credit balance was paid back to the resident from a cash or bank account.
 */
final class CreditRefunded
{
    use Dispatchable;

    public function __construct(
        public readonly CreditTransaction $entry,
        public readonly string $accountId,
    ) {}
}
