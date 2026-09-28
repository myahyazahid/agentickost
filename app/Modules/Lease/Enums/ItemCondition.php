<?php

namespace App\Modules\Lease\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Condition of one item of a room inspection (FR-SIK-01, FR-SIK-04).
 */
enum ItemCondition: string implements HasLabel
{
    case Good = 'good';
    case Fair = 'fair';
    case Damaged = 'damaged';
    case Missing = 'missing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Good => 'Baik',
            self::Fair => 'Cukup',
            self::Damaged => 'Rusak',
            self::Missing => 'Hilang',
        };
    }

    /**
     * Items that can carry a damage charge at check-out.
     */
    public function isChargeable(): bool
    {
        return in_array($this, [self::Damaged, self::Missing], true);
    }
}
