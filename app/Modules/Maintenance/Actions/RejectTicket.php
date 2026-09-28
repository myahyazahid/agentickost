<?php

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\States\Ticket\Rejected;
use App\Modules\Maintenance\Support\TicketLog;
use App\Support\Actions\Action;

/**
 * Turns down a new ticket that needs no work, such as a duplicate or a
 * resident's own item, with the reason on the ticket's history.
 */
final class RejectTicket extends Action
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

            $this->log->move($ticket, Rejected::class, $data['reason'], 'reason');

            return $ticket;
        });
    }
}
