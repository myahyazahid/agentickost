<?php

namespace App\Modules\Lease\Events;

use App\Modules\Lease\Models\Contract;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A contract started: the room is occupied and billing can begin.
 */
final class ContractActivated
{
    use Dispatchable;

    public function __construct(public readonly Contract $contract) {}
}
