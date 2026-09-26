<?php

namespace App\Modules\Billing\Engine;

use App\Modules\Property\Enums\PenaltyType;

/**
 * A property's late penalty settings (PRD §8.4). The percentage is a decimal
 * string such as "2.50", as stored in DECIMAL(5,2).
 */
final readonly class PenaltyRules
{
    public function __construct(
        public PenaltyType $type,
        public int $graceDays,
        public ?int $amount = null,
        public ?string $percent = null,
        public ?int $maxAmount = null,
    ) {}
}
