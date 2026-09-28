<?php

namespace App\Modules\Lease\Enums;

use Filament\Support\Contracts\HasLabel;

enum InspectionType: string implements HasLabel
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';

    public function getLabel(): string
    {
        return match ($this) {
            self::CheckIn => 'Check-in',
            self::CheckOut => 'Check-out',
        };
    }
}
