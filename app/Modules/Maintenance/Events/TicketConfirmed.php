<?php

namespace App\Modules\Maintenance\Events;

use App\Modules\Maintenance\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A repair was accepted; its cost is booked (FR-MNT-04).
 */
final class TicketConfirmed
{
    use Dispatchable;

    public function __construct(public readonly Ticket $ticket) {}
}
