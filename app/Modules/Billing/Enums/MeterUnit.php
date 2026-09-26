<?php

namespace App\Modules\Billing\Enums;

use Filament\Support\Contracts\HasLabel;

enum MeterUnit: string implements HasLabel
{
    case Kwh = 'kwh';
    case CubicMeter = 'm3';

    public function getLabel(): string
    {
        return match ($this) {
            self::Kwh => 'kWh',
            self::CubicMeter => 'm³',
        };
    }
}
