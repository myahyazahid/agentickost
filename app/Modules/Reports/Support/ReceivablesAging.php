<?php

namespace App\Modules\Reports\Support;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Property\Models\Property;

/**
 * Unpaid invoice balances by how long they are overdue (FR-RPT-03), one row
 * per contract, in each property's own calendar.
 */
final class ReceivablesAging
{
    /**
     * @var array<string, string> bucket key => label
     */
    public const BUCKETS = [
        'current' => 'Belum jatuh tempo',
        'days_1_30' => '1–30 hari',
        'days_31_60' => '31–60 hari',
        'days_over_60' => 'Lebih dari 60 hari',
    ];

    /**
     * @return list<array{room: string, payer: string, current: int, days_1_30: int, days_31_60: int, days_over_60: int, total: int}>
     */
    public static function rows(User $user, ?string $propertyId): array
    {
        $properties = Property::query()
            ->accessibleBy($user)
            ->when($propertyId !== null, fn ($query) => $query->whereKey($propertyId))
            ->get()
            ->keyBy('id');

        $invoices = Invoice::query()
            ->whereIn('status', InvoiceState::openValues())
            ->where('balance_amount', '>', 0)
            ->whereIn('property_id', $properties->keys())
            ->with(['contract.room', 'payer', 'property'])
            ->orderBy('due_date')
            ->get();

        $rows = [];

        foreach ($invoices as $invoice) {
            $property = $properties->get($invoice->property_id);

            if (! $property instanceof Property) {
                continue;
            }

            $key = $invoice->contract_id ?? "payer-{$invoice->payer_id}";
            $room = $invoice->contract?->room?->number;
            $row = $rows[$key] ?? self::emptyRow(
                ($room !== null ? "Kamar {$room}, " : '').$property->name,
                (string) $invoice->payer?->name,
            );

            $bucket = self::bucket((int) $invoice->due_date->diffInDays($property->today(), false));
            $row[$bucket] += $invoice->balance_amount;
            $row['total'] += $invoice->balance_amount;
            $rows[$key] = $row;
        }

        $rows = array_values($rows);
        usort($rows, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return $rows;
    }

    /**
     * @return array{room: string, payer: string, current: int, days_1_30: int, days_31_60: int, days_over_60: int, total: int}
     */
    private static function emptyRow(string $room, string $payer): array
    {
        return ['room' => $room, 'payer' => $payer, 'current' => 0, 'days_1_30' => 0, 'days_31_60' => 0, 'days_over_60' => 0, 'total' => 0];
    }

    /**
     * @param  int  $daysLate  days from the due date to today; zero or less is not late yet
     * @return 'current'|'days_1_30'|'days_31_60'|'days_over_60'
     */
    public static function bucket(int $daysLate): string
    {
        return match (true) {
            $daysLate <= 0 => 'current',
            $daysLate <= 30 => 'days_1_30',
            $daysLate <= 60 => 'days_31_60',
            default => 'days_over_60',
        };
    }
}
