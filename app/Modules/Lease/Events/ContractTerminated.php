<?php

namespace App\Modules\Lease\Events;

use App\Modules\Lease\Models\Contract;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A contract ended early (FR-KTR-04); settlement follows at check-out.
 */
final class ContractTerminated
{
    use Dispatchable;

    public function __construct(public readonly Contract $contract) {}
}
