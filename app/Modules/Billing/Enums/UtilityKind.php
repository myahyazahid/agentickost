<?php

namespace App\Modules\Billing\Enums;

use Filament\Support\Contracts\HasLabel;

enum UtilityKind: string implements HasLabel
{
    case Electricity = 'electricity';
    case Water = 'water';
    case Internet = 'internet';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Electricity => 'Listrik',
            self::Water => 'Air',
            self::Internet => 'Internet',
            self::Other => 'Lainnya',
        };
    }
}
