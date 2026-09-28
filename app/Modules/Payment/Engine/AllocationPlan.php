<?php

namespace App\Modules\Payment\Engine;

/**
 * The result of allocating an amount: the lines to write, and what is left
 * for the credit balance.
 */
final readonly class AllocationPlan
{
    /**
     * @param  list<AllocationLine>  $lines
     */
    public function __construct(
        public array $lines,
        public int $remainder,
    ) {}

    public function allocated(): int
    {
        return array_sum(array_map(fn (AllocationLine $line): int => $line->amount, $this->lines));
    }

    public function allocatedTo(string $invoiceId): int
    {
        $total = 0;

        foreach ($this->lines as $line) {
            if ($line->invoiceId === $invoiceId) {
                $total += $line->amount;
            }
        }

        return $total;
    }

    /**
     * @return list<string>
     */
    public function invoiceIds(): array
    {
        return array_values(array_unique(array_map(fn (AllocationLine $line): string => $line->invoiceId, $this->lines)));
    }
}
