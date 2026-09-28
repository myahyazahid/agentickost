<?php

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Events\TicketConfirmed;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\States\Ticket\Done;
use App\Modules\Maintenance\Support\TicketCharges;
use App\Modules\Maintenance\Support\TicketLog;
use App\Support\Actions\Action;

/**
 * Accepts the repair (PRD §9.5). Its cost becomes a maintenance expense and,
 * when marked, a charge to the resident, in the same transaction
 * (FR-MNT-04, FR-MNT-05).
 */
final class ConfirmTicket extends Action
{
    public function __construct(
        private readonly TicketLog $log,
        private readonly TicketCharges $charges,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Ticket $ticket, array $input = []): Ticket
    {
        $this->authorize('manage', $ticket);

        $data = $this->validate($input, [
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->transaction(function () use ($ticket, $data): Ticket {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $ticket->confirmed_at = now();
            $this->log->move($ticket, Done::class, $data['note'] ?? null, 'note');
            $this->charges->book($ticket);

            TicketConfirmed::dispatch($ticket);

            return $ticket;
        });
    }
}
