<?php

namespace App\Modules\Property\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * PRD §8.3.
 */
enum ProrationBasis: string implements HasLabel
{
    case ActualDays = 'actual_days';
    case ThirtyDays = 'thirty_days';

    public function getLabel(): string
    {
        return match ($this) {
            self::ActualDays => 'Jumlah hari sebenarnya dalam bulan',
            self::ThirtyDays => 'Selalu 30 hari',
        };
    }
}
