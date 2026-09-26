<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Support\AdhocItems;
use App\Modules\Billing\Support\InvoiceIssuer;
use App\Modules\Lease\Models\Contract;
use App\Modules\Property\Models\Property;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * A manual invoice, such as a damage charge (FR-BIL-05). It starts as a draft
 * that can still be edited, unless it is issued right away.
 */
final class CreateAdhocInvoice extends Action
{
    public function __construct(private readonly InvoiceIssuer $issuer) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Property $property, array $input): Invoice
    {
        $this->authorize('createIn', [Invoice::class, $property]);

        $data = $this->validate($input, [
            'contract_id' => ['required', 'string'],
            'due_date' => ['required', 'date'],
            'issue_now' => ['sometimes', 'boolean'],
            ...AdhocItems::rules(),
        ]);

        $contract = Contract::query()
            ->where('property_id', $property->id)
            ->whereKey($data['contract_id'])
            ->first();

        if ($contract === null) {
            throw ValidationException::withMessages(['contract_id' => 'Kontrak tidak ditemukan di properti ini.']);
        }

        return $this->transaction(function () use ($property, $contract, $data): Invoice {
            $invoice = Invoice::create([
                'property_id' => $property->id,
                'contract_id' => $contract->id,
                'payer_id' => $contract->payer_id,
                'type' => InvoiceType::Adhoc,
                'due_date' => $data['due_date'],
            ]);

            AdhocItems::replace($invoice, $data['items']);

            if ($data['issue_now'] ?? false) {
                $this->issuer->issue($invoice, $property->today());
            }

            return $invoice;
        });
    }
}
