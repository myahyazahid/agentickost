<?php

namespace App\Modules\Payment\Listeners;

use App\Modules\Billing\Events\InvoiceIssued;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Support\CreditLedger;

/**
 * Credit left from an overpayment pays the next invoice as soon as it is
 * issued (FR-PAY-05). Runs inside the issuing transaction.
 */
final class ApplyCreditToIssuedInvoice
{
    public function __construct(private readonly CreditLedger $credit) {}

    public function handle(InvoiceIssued $event): void
    {
        $invoice = $event->invoice;

        if ($invoice->contract_id === null || CreditLedger::balance($invoice->contract_id) <= 0) {
            return;
        }

        $contract = Contract::query()->whereKey($invoice->contract_id)->lockForUpdate()->firstOrFail();

        $this->credit->applyToOpenInvoices($contract, $invoice);
    }
}
