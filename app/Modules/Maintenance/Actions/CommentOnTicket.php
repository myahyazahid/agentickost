<?php

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\Models\TicketUpdate;
use App\Modules\Maintenance\Support\TicketLog;
use App\Support\Actions\Action;

/**
 * Adds a note to a ticket's history, such as a part being ordered.
 */
final class CommentOnTicket extends Action
{
    public function __construct(private readonly TicketLog $log) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Ticket $ticket, array $input): TicketUpdate
    {
        $this->authorize('comment', $ticket);

        $data = $this->validate($input, [
            'note' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        return $this->transaction(fn (): TicketUpdate => $this->log->note($ticket, $data['note']));
    }
}
