<?php

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\States\Ticket\InProgress;
use App\Modules\Maintenance\Support\TicketLog;
use App\Support\Actions\Action;

/**
 * The assigned staff member starts the repair (PRD §9.5).
 */
final class StartTicketWork extends Action
{
    public function __construct(private readonly TicketLog $log) {}

    public function handle(Ticket $ticket): Ticket
    {
        // Who may work on it depends on the current assignee, not the caller's copy.
        $ticket->refresh();

        $this->authorize('work', $ticket);

        return $this->transaction(function () use ($ticket): Ticket {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $this->log->move($ticket, InProgress::class);

            return $ticket;
        });
    }
}
