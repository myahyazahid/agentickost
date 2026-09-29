<?php

namespace App\Modules\Portal\Livewire;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Maintenance\States\Ticket\TicketState;
use App\Modules\Payment\States\Payment\Pending;
use App\Modules\Portal\Support\PortalQueries;
use Illuminate\Contracts\View\View;

/**
 * The first screen after login: what is still owed and when, then recent
 * announcements and open repair reports for residents (FR-PRT-02,
 * FR-PRT-04, FR-PRT-05).
 */
class Home extends PortalPage
{
    public function render(): View
    {
        $access = $this->access();
        $open = PortalQueries::invoices($access)
            ->whereIn('status', InvoiceState::openValues())
            ->where('balance_amount', '>', 0)
            ->with('property')
            ->orderBy('due_date')
            ->get();

        return $this->page('portal::livewire.home', 'Beranda', [
            'account' => $this->account(),
            'owed' => (int) $open->sum('balance_amount'),
            'nextInvoice' => $open->first(),
            'overdueCount' => $open->filter(fn (Invoice $invoice): bool => $invoice->isOverdue())->count(),
            'announcements' => $access->isResident ? PortalQueries::announcements($access)->limit(2)->get() : collect(),
            'openTickets' => $access->isResident ? PortalQueries::tickets($access)->whereIn('status', TicketState::openValues())->count() : 0,
            'pendingPayments' => PortalQueries::payments($access)->where('status', Pending::$name)->count(),
        ]);
    }
}
