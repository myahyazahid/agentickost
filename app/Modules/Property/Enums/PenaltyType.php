<?php

namespace App\Modules\Property\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * PRD §8.4.
 */
enum PenaltyType: string implements HasLabel
{
    case None = 'none';

    /** A fixed amount, once, after the grace period. */
    case Flat = 'flat';

    /** A fixed amount for every day after the grace period. */
    case Daily = 'daily';

    /** A percentage of the unpaid balance, once, after the grace period. */
    case Percent = 'percent';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Tanpa denda',
            self::Flat => 'Nominal tetap, sekali',
            self::Daily => 'Nominal per hari',
            self::Percent => 'Persen dari sisa tagihan, sekali',
        };
    }
}
