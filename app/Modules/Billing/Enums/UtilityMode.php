<?php

namespace App\Modules\Billing\Enums;

use Filament\Support\Contracts\HasLabel;

enum UtilityMode: string implements HasLabel
{
    case Metered = 'metered';
    case Token = 'token';
    case Flat = 'flat';

    public function getLabel(): string
    {
        return match ($this) {
            self::Metered => 'Meteran per kamar (pascabayar)',
            self::Token => 'Token prabayar',
            self::Flat => 'Tarif tetap per bulan',
        };
    }
}
