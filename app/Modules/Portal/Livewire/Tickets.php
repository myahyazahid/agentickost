<?php

namespace App\Modules\Portal\Livewire;

use App\Modules\Maintenance\States\Ticket\TicketState;
use App\Modules\Portal\Support\PortalQueries;
use Illuminate\Contracts\View\View;

/**
 * Repair reports for the resident's room and the ones they sent, open
 * ones first (FR-PRT-04). Payers who live elsewhere have no reports.
 */
class Tickets extends PortalPage
{
    public function mount(): void
    {
        abort_unless($this->access()->isResident, 404);
    }

    public function render(): View
    {
        return $this->page('portal::livewire.tickets', 'Laporan kerusakan', [
            'tickets' => PortalQueries::tickets($this->access())
                ->with('room')
                ->orderByRaw('CASE WHEN status IN ('.implode(', ', array_fill(0, count(TicketState::openValues()), '?')).') THEN 0 ELSE 1 END', TicketState::openValues())
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
        ]);
    }
}
