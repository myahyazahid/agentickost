<?php

namespace App\Modules\Lease\Support;

use App\Modules\Lease\Enums\ItemCondition;
use App\Modules\Lease\Models\Inspection;
use App\Modules\Lease\Models\InspectionItem;
use Illuminate\Validation\Rule;

/**
 * The checklist of a room inspection: shared by check-in and check-out.
 */
final class InspectionItems
{
    /**
     * Items most kost rooms have, offered as a starting checklist.
     */
    public const DEFAULTS = [
        'Kasur dan dipan',
        'Lemari',
        'Meja dan kursi',
        'Lampu dan stop kontak',
        'Kipas angin atau AC',
        'Kamar mandi dan keran',
        'Pintu, jendela, dan kunci',
        'Dinding dan lantai',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $withCharges): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.item_name' => ['required', 'string', 'max:100'],
            'items.*.condition' => ['required', Rule::enum(ItemCondition::class)],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            ...($withCharges ? ['items.*.charge_amount' => ['nullable', 'integer', 'min:0']] : []),
        ];
    }

    /**
     * @param  array<array-key, array<string, mixed>>  $items  Validated rows, keyed as the form sent them
     */
    public static function write(Inspection $inspection, array $items): void
    {
        foreach (array_values($items) as $index => $item) {
            InspectionItem::create([
                'inspection_id' => $inspection->id,
                'item_name' => $item['item_name'],
                'condition' => $item['condition'],
                'charge_amount' => (int) ($item['charge_amount'] ?? 0),
                'notes' => $item['notes'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * The default checklist as form rows, every item in good condition.
     *
     * @return list<array{item_name: string, condition: string}>
     */
    public static function defaultRows(): array
    {
        return array_map(fn (string $name): array => ['item_name' => $name, 'condition' => ItemCondition::Good->value], self::DEFAULTS);
    }
}
