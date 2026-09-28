<?php

namespace App\Modules\Maintenance\Support;

use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\Models\TicketUpdate;
use App\Modules\Maintenance\States\Ticket\TicketState;
use App\Support\Actors\ActorContext;
use App\Support\States\StateTransition;

/**
 * Moves a ticket through its states and writes each step, with its note, to
 * the ticket's history. Call inside the Action's transaction.
 */
final class TicketLog
{
    public function __construct(private readonly ActorContext $actors) {}

    /**
     * @param  class-string<TicketState>  $to
     */
    public function move(Ticket $ticket, string $to, ?string $note = null, string $errorKey = 'status'): void
    {
        $from = $ticket->status->getValue();

        StateTransition::to($ticket->status, $to, $errorKey);

        $this->write($ticket, $from, $ticket->status->getValue(), $note);
    }

    public function note(Ticket $ticket, string $note): TicketUpdate
    {
        return $this->write($ticket, null, null, $note);
    }

    private function write(Ticket $ticket, ?string $from, ?string $to, ?string $note): TicketUpdate
    {
        $actor = $this->actors->current();

        return TicketUpdate::create([
            'ticket_id' => $ticket->id,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }
}
