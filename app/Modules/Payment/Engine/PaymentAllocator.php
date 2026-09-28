<?php

namespace App\Modules\Payment\Engine;

use App\Modules\Property\Enums\AllocationCategory;
use InvalidArgumentException;

/**
 * Splits money over open invoices (PRD §8.5): the oldest invoice first, and
 * inside an invoice by the property's category order. An invoice never gets
 * more than it owes in total, so a negative line (discount, rounding) lowers
 * what its positive categories receive. Pure: reads and writes nothing.
 */
final class PaymentAllocator
{
    /**
     * @param  list<OpenInvoice>  $invoices
     * @param  list<AllocationCategory>  $order
     * @param  list<AllocationCategory>  $except  Categories this money may not pay
     */
    public static function allocate(int $amount, array $invoices, array $order, array $except = []): AllocationPlan
    {
        $lines = [];
        $left = $amount;

        foreach (self::oldestFirst($invoices) as $invoice) {
            if ($left <= 0) {
                break;
            }

            $invoiceLines = self::fill($invoice, $left, $order, $except);
            $left -= array_sum(array_map(fn (AllocationLine $line): int => $line->amount, $invoiceLines));
            $lines = [...$lines, ...$invoiceLines];
        }

        return new AllocationPlan($lines, $left);
    }

    /**
     * Pays the chosen invoices exactly the chosen amounts, each still in the
     * category order.
     *
     * @param  list<OpenInvoice>  $invoices
     * @param  array<string, int>  $targets  Amount per invoice id
     * @param  list<AllocationCategory>  $order
     * @param  list<AllocationCategory>  $except
     *
     * @throws InvalidArgumentException when a target is not open, gets more than it owes, or the targets exceed the amount
     */
    public static function allocateTo(int $amount, array $invoices, array $targets, array $order, array $except = []): AllocationPlan
    {
        if (array_sum($targets) > $amount) {
            throw new InvalidArgumentException('Jumlah yang dialokasikan melebihi nominal pembayaran.');
        }

        $byId = [];

        foreach ($invoices as $invoice) {
            $byId[$invoice->id] = $invoice;
        }

        foreach (array_keys($targets) as $invoiceId) {
            if (! isset($byId[$invoiceId])) {
                throw new InvalidArgumentException("Tagihan {$invoiceId} tidak sedang menunggu pembayaran.");
            }
        }

        $lines = [];

        foreach (self::oldestFirst(array_values(array_intersect_key($byId, $targets))) as $invoice) {
            $wanted = $targets[$invoice->id];
            $invoiceLines = self::fill($invoice, $wanted, $order, $except);

            if (array_sum(array_map(fn (AllocationLine $line): int => $line->amount, $invoiceLines)) < $wanted) {
                throw new InvalidArgumentException("Tagihan {$invoice->id} tidak memiliki sisa sebanyak itu.");
            }

            $lines = [...$lines, ...$invoiceLines];
        }

        return new AllocationPlan($lines, $amount - array_sum($targets));
    }

    /**
     * @param  list<AllocationCategory>  $order
     * @param  list<AllocationCategory>  $except
     * @return list<AllocationLine>
     */
    private static function fill(OpenInvoice $invoice, int $available, array $order, array $except): array
    {
        $cap = min($available, $invoice->total());
        $lines = [];

        foreach ($order as $category) {
            if ($cap <= 0) {
                break;
            }

            if (in_array($category, $except, true)) {
                continue;
            }

            $amount = min($cap, max(0, $invoice->owedIn($category->value)));

            if ($amount > 0) {
                $lines[] = new AllocationLine($invoice->id, $category, $amount);
                $cap -= $amount;
            }
        }

        return $lines;
    }

    /**
     * @param  list<OpenInvoice>  $invoices
     * @return list<OpenInvoice>
     */
    private static function oldestFirst(array $invoices): array
    {
        usort($invoices, fn (OpenInvoice $a, OpenInvoice $b): int => [$a->dueDate->getTimestamp(), $a->id] <=> [$b->dueDate->getTimestamp(), $b->id]);

        return $invoices;
    }
}
