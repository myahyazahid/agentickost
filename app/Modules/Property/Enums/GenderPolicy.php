<?php

namespace App\Modules\Property\Enums;

use Filament\Support\Contracts\HasLabel;

enum GenderPolicy: string implements HasLabel
{
    case Male = 'male';
    case Female = 'female';
    case Mixed = 'mixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Male => 'Putra',
            self::Female => 'Putri',
            self::Mixed => 'Campur',
        };
    }
}
