<?php

namespace App\Modules\Billing\Engine;

use App\Modules\Property\Enums\BillingMode;
use App\Modules\Property\Enums\ProrationBasis;
use App\Modules\Property\Enums\RentalPeriod;

/**
 * The billing settings that shape a contract's periods (PRD §8.2, §8.3).
 * The anchor day is the contract's billing_anchor_day: its start day in
 * anniversary mode, the property's fixed day in fixed date mode.
 */
final readonly class BillingRules
{
    public function __construct(
        public RentalPeriod $period,
        public BillingMode $mode,
        public int $anchorDay,
        public ProrationBasis $basis,
        public int $leadDays,
    ) {}

    /**
     * Fixed date billing only applies to month-based periods (docs/adr/0008).
     */
    public function usesFixedDate(): bool
    {
        return $this->mode === BillingMode::FixedDate && $this->period->isMonthBased();
    }
}
