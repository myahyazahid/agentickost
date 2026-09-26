<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Enums\InvoiceItemType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use Illuminate\Validation\Rule;

/**
 * Lines typed in by staff on a manual invoice. Amounts are entered as
 * positive numbers; a discount is stored negative.
 */
final class AdhocItems
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.type' => ['required', Rule::in(array_keys(InvoiceItemType::manualOptions()))],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @param  array<array-key, array{type: string, description: string, amount: int|string}>  $items
     */
    public static function replace(Invoice $invoice, array $items): void
    {
        $invoice->items()->get()->each->delete();

        foreach (array_values($items) as $index => $item) {
            $type = InvoiceItemType::from($item['type']);
            $amount = (int) $item['amount'] * ($type === InvoiceItemType::Discount ? -1 : 1);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'type' => $type,
                'allocation_category' => $type->allocationCategory(),
                'description' => $item['description'],
                'quantity' => 1,
                'unit_amount' => $amount,
                'amount' => $amount,
                'sort_order' => $index,
            ]);
        }
    }
}
