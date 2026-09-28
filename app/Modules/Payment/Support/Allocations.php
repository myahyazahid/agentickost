<?php

namespace App\Modules\Payment\Support;

/**
 * Manual allocation input shared by the payment actions: a list of
 * {invoice_id, amount}. An empty list means "allocate automatically".
 */
final class Allocations
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'allocations' => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['required', 'string'],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  Validated input
     * @return array<string, int>|null Amount per invoice id, or null for automatic allocation
     */
    public static function targets(array $data): ?array
    {
        $rows = $data['allocations'] ?? [];

        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $targets = [];

        foreach ($rows as $row) {
            $invoiceId = (string) $row['invoice_id'];
            $targets[$invoiceId] = ($targets[$invoiceId] ?? 0) + (int) $row['amount'];
        }

        return $targets;
    }
}
