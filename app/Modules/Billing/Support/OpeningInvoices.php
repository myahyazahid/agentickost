<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Enums\InvoiceItemType;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Lease\Models\Contract;
use Carbon\CarbonImmutable;

/**
 * Arrears carried in at onboarding (FR-ONB-04). Each becomes an issued
 * invoice dated on the cut-off date, so later payments are allocated to it
 * like any other invoice (schema §10.8). The opening balance journal books
 * the receivable; the invoice itself posts no journal and accrues no penalty.
 * Call inside the Action's transaction.
 */
final class OpeningInvoices
{
    public function __construct(private readonly InvoiceIssuer $issuer) {}

    public function issue(Contract $contract, int $amount, CarbonImmutable $cutoff, ?string $note = null): Invoice
    {
        $invoice = Invoice::create([
            'property_id' => $contract->property_id,
            'contract_id' => $contract->id,
            'payer_id' => $contract->payer_id,
            'type' => InvoiceType::Opening,
            'due_date' => $cutoff,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceItemType::Rent,
            'allocation_category' => InvoiceItemType::Rent->allocationCategory(),
            'description' => mb_substr('Tunggakan per '.$cutoff->translatedFormat('j F Y').($note !== null ? ": {$note}" : ''), 0, 255),
            'quantity' => 1,
            'unit_amount' => $amount,
            'amount' => $amount,
            'sort_order' => 0,
        ]);

        return $this->issuer->issue($invoice, $cutoff);
    }
}
