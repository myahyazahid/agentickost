<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Engine\BillingPeriod;
use App\Modules\Lease\Models\Contract;

/**
 * A rent invoice that will be issued, as shown in the preview before bulk
 * issuing. Utilities are an estimate: readings may still be added.
 */
final readonly class UpcomingInvoice
{
    public function __construct(
        public Contract $contract,
        public BillingPeriod $period,
        public int $rent,
        public int $deposit,
        public int $utilities,
    ) {}

    public function total(): int
    {
        return $this->rent + $this->deposit + $this->utilities;
    }
}
