<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Engine\TotalRounding;
use App\Modules\Billing\Enums\InvoiceItemType;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Events\InvoiceIssued;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Billing\States\Invoice\Draft;
use App\Modules\Billing\States\Invoice\Issued;
use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Support\DocumentNumbers;
use App\Support\Actors\ActorContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Turns a draft into an issued invoice: rounds the total to the property's
 * unit on its own line (PRD §8.1), numbers it (FR-BIL-07), and records who
 * issued it. Opening arrears keep the exact amount the owner entered, so
 * they match the opening journal. Call inside the Action's transaction.
 */
final class InvoiceIssuer
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly ActorContext $actors,
    ) {}

    public function issue(Invoice $invoice, CarbonInterface $issueDate): Invoice
    {
        if (! $invoice->status->equals(Draft::class)) {
            throw ValidationException::withMessages(['status' => 'Hanya tagihan draf yang bisa diterbitkan.']);
        }

        $property = $invoice->property()->firstOrFail();
        $total = (int) $invoice->items()->sum('amount');

        if (! $invoice->items()->exists()) {
            throw ValidationException::withMessages(['items' => 'Tagihan belum punya rincian.']);
        }

        $rounding = $invoice->type === InvoiceType::Opening
            ? 0
            : TotalRounding::difference($total, $property->resolvedSettings()->rounding_unit);

        if ($rounding !== 0) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'type' => InvoiceItemType::Rounding,
                'allocation_category' => InvoiceItemType::Rounding->allocationCategory(),
                'description' => 'Pembulatan',
                'quantity' => 1,
                'unit_amount' => $rounding,
                'amount' => $rounding,
                'sort_order' => 999,
            ]);
            $total += $rounding;
        }

        if ($total < 0) {
            throw ValidationException::withMessages(['items' => 'Total tagihan tidak boleh kurang dari nol.']);
        }

        $actor = $this->actors->current();

        $invoice->items_total_amount = $total;
        $invoice->issue_date = Carbon::parse($issueDate->toDateString());
        $invoice->number = $this->numbers->next(DocumentType::Invoice, $issueDate, $property->code);
        $invoice->issued_by_type = $actor->type;
        $invoice->issued_by_id = $actor->id;
        $invoice->status->transitionTo(Issued::class);

        InvoiceBalance::sync($invoice);

        InvoiceIssued::dispatch($invoice);

        return $invoice;
    }
}
