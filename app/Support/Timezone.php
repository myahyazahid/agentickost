<?php

namespace App\Support;

use Filament\Support\Contracts\HasLabel;

/**
 * Indonesian time zones a tenant or property can run in (NFR-LOC-02).
 */
enum Timezone: string implements HasLabel
{
    case Wib = 'Asia/Jakarta';
    case Wita = 'Asia/Makassar';
    case Wit = 'Asia/Jayapura';

    public function getLabel(): string
    {
        return match ($this) {
            self::Wib => 'WIB (Asia/Jakarta)',
            self::Wita => 'WITA (Asia/Makassar)',
            self::Wit => 'WIT (Asia/Jayapura)',
        };
    }
}
