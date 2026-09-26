<?php

namespace App\Modules\Billing\Engine;

use Carbon\CarbonImmutable;

/**
 * One billing period of a contract. Rent for the period is the period rent
 * times billedDays / basisDays (PRD §8.3); for a full period both are equal.
 */
final readonly class BillingPeriod
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $dueDate,
        public CarbonImmutable $issueDate,
        public int $billedDays,
        public int $basisDays,
    ) {}

    public function isPartial(): bool
    {
        return $this->billedDays < $this->basisDays;
    }
}
