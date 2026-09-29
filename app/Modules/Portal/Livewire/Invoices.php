<?php

namespace App\Modules\Portal\Livewire;

use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Portal\Support\PortalQueries;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;

/**
 * Bills of the contracts this login can see, unpaid ones first
 * (FR-PRT-02, FR-PRT-07).
 */
class Invoices extends PortalPage
{
    #[Url(as: 'tampil')]
    public string $show = 'belum-lunas';

    public function render(): View
    {
        $query = PortalQueries::invoices($this->access())->with(['property', 'contract.room']);

        if ($this->show !== 'semua') {
            $query->whereIn('status', InvoiceState::openValues())->where('balance_amount', '>', 0);
        }

        return $this->page('portal::livewire.invoices', 'Tagihan', [
            'invoices' => $query->orderByDesc('due_date')->limit(60)->get(),
        ]);
    }
}
