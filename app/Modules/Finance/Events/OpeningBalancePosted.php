<?php

namespace App\Modules\Finance\Events;

use App\Modules\Finance\Models\OpeningBalance;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Balances from before KostPilot were carried into the ledgers.
 */
final class OpeningBalancePosted
{
    use Dispatchable;

    public function __construct(public readonly OpeningBalance $openingBalance) {}
}
