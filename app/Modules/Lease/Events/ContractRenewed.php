<?php

namespace App\Modules\Lease\Events;

use App\Modules\Lease\Models\Contract;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A renewal took over from the previous contract (FR-KTR-03). Deposits move
 * from the previous contract to the renewal.
 */
final class ContractRenewed
{
    use Dispatchable;

    public function __construct(
        public readonly Contract $previous,
        public readonly Contract $renewal,
    ) {}
}
