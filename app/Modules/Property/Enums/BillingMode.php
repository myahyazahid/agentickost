<?php

namespace App\Modules\Property\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * PRD §8.2.
 */
enum BillingMode: string implements HasLabel
{
    /** Due on the contract start date each period. */
    case Anniversary = 'anniversary';

    /** Everyone is due on the same day of the month; the first period is prorated. */
    case FixedDate = 'fixed_date';

    public function getLabel(): string
    {
        return match ($this) {
            self::Anniversary => 'Mengikuti tanggal masuk',
            self::FixedDate => 'Tanggal tetap',
        };
    }
}
