<?php

namespace App\Modules\Lease\Enums;

use Filament\Support\Contracts\HasLabel;

enum ShareType: string implements HasLabel
{
    case Equal = 'equal';
    case Fixed = 'fixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Equal => 'Dibagi rata',
            self::Fixed => 'Nominal tetap',
        };
    }
}
