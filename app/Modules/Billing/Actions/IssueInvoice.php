<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Support\InvoiceIssuer;
use App\Support\Actions\Action;

final class IssueInvoice extends Action
{
    public function __construct(private readonly InvoiceIssuer $issuer) {}

    public function handle(Invoice $invoice): Invoice
    {
        $this->authorize('update', $invoice);

        $today = $invoice->property()->firstOrFail()->today();

        return $this->transaction(fn (): Invoice => $this->issuer->issue($invoice, $today));
    }
}
