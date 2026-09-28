<?php

namespace App\Modules\Maintenance\Events;

use App\Modules\Maintenance\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A repair or cleaning job was reported (FR-MNT-01).
 */
final class TicketReported
{
    use Dispatchable;

    public function __construct(public readonly Ticket $ticket) {}
}
