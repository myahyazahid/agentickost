<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Enums\InvoiceItemType;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Lease\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Bills a resident for damage they caused, as an issued manual invoice with
 * the source of the charge on its line (FR-BIL-05, FR-MNT-05). Called by
 * other modules' actions inside their transaction.
 */
final class DamageCharges
{
    public function __construct(private readonly InvoiceIssuer $issuer) {}

    public function charge(Contract $contract, string $description, int $amount, Model $source, CarbonImmutable $today, int $dueInDays = 7): Invoice
    {
        $invoice = Invoice::create([
            'property_id' => $contract->property_id,
            'contract_id' => $contract->id,
            'payer_id' => $contract->payer_id,
            'type' => InvoiceType::Adhoc,
            'due_date' => $today->addDays($dueInDays),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceItemType::Damage,
            'allocation_category' => InvoiceItemType::Damage->allocationCategory(),
            'description' => mb_substr($description, 0, 255),
            'quantity' => 1,
            'unit_amount' => $amount,
            'amount' => $amount,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'sort_order' => 0,
        ]);

        return $this->issuer->issue($invoice, $today);
    }
}
