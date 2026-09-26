<?php

namespace App\Modules\Lease\Events;

use App\Modules\Lease\Models\Contract;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A contract reached its end, or was replaced by its renewal.
 */
final class ContractCompleted
{
    use Dispatchable;

    public function __construct(public readonly Contract $contract) {}
}
