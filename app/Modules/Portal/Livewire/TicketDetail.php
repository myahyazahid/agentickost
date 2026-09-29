<?php

namespace App\Modules\Portal\Livewire;

use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\Models\TicketUpdate;
use App\Modules\Maintenance\States\Ticket\TicketState;
use App\Modules\Portal\Support\PortalQueries;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;

/**
 * One repair report and how it moved (FR-PRT-04). Staff notes stay with
 * staff: the resident sees the status changes, not what was written
 * between them.
 */
class TicketDetail extends PortalPage
{
    #[Locked]
    public string $ticketId = '';

    public function mount(string $ticket): void
    {
        abort_unless($this->access()->isResident, 404);

        $this->ticketId = $ticket;
        $this->ticket();
    }

    public function render(): View
    {
        $ticket = $this->ticket();

        return $this->page('portal::livewire.ticket', $ticket->title, [
            'ticket' => $ticket,
            'steps' => TicketUpdate::query()
                ->where('ticket_id', $ticket->id)
                ->whereNotNull('to_status')
                ->orderBy('created_at')
                ->get()
                ->map(fn (TicketUpdate $update): array => [
                    'label' => self::statusLabel((string) $update->to_status),
                    'at' => $update->created_at,
                ]),
            'photoCount' => $ticket->attachments()->count(),
        ]);
    }

    private function ticket(): Ticket
    {
        return PortalQueries::tickets($this->access())->with('room')->whereKey($this->ticketId)->firstOrFail();
    }

    private static function statusLabel(string $value): string
    {
        $state = TicketState::make($value, new Ticket);

        return $state instanceof TicketState ? $state->getLabel() : $value;
    }
}
