<?php

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\States\Ticket\InProgress;
use App\Modules\Maintenance\Support\TicketLog;
use App\Support\Actions\Action;

/**
 * The repair did not hold: the ticket goes back to work with a reason
 * (PRD §9.5). The cost is entered again when it is resolved.
 */
final class ReopenTicket extends Action
{
    public function __construct(private readonly TicketLog $log) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Ticket $ticket, array $input): Ticket
    {
        $this->authorize('manage', $ticket);

        $data = $this->validate($input, [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return $this->transaction(function () use ($ticket, $data): Ticket {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $ticket->resolved_at = null;
            $this->log->move($ticket, InProgress::class, $data['reason'], 'reason');

            return $ticket;
        });
    }
}
