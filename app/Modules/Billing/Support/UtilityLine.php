<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\MeterReading;

/**
 * One utility line before it is written, with the reading it bills.
 */
final readonly class UtilityLine
{
    /**
     * @param  array<string, mixed>  $attributes  invoice item attributes
     */
    public function __construct(
        public array $attributes,
        public ?MeterReading $reading = null,
    ) {}

    public function amount(): int
    {
        return (int) $this->attributes['amount'];
    }
}
