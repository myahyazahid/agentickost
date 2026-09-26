<?php

namespace App\Modules\Billing\Engine;

use Carbon\CarbonImmutable;

final readonly class PenaltyAccrualDraft
{
    public function __construct(
        public CarbonImmutable $date,
        public int $amount,
    ) {}
}
