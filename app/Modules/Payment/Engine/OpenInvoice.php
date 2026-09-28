<?php

namespace App\Modules\Payment\Engine;

use Carbon\CarbonImmutable;

/**
 * What an invoice still owes, per allocation category value. A category can
 * be negative when a discount or rounding line outweighs it.
 */
final readonly class OpenInvoice
{
    /**
     * @param  array<string, int>  $outstanding
     */
    public function __construct(
        public string $id,
        public CarbonImmutable $dueDate,
        public array $outstanding,
    ) {}

    public function total(): int
    {
        return array_sum($this->outstanding);
    }

    public function owedIn(string $category): int
    {
        return $this->outstanding[$category] ?? 0;
    }
}
