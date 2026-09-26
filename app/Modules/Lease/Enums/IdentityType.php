<?php

namespace App\Modules\Lease\Enums;

use Filament\Support\Contracts\HasLabel;

enum IdentityType: string implements HasLabel
{
    case Ktp = 'ktp';
    case Sim = 'sim';
    case Passport = 'passport';
    case StudentCard = 'student_card';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ktp => 'KTP',
            self::Sim => 'SIM',
            self::Passport => 'Paspor',
            self::StudentCard => 'Kartu pelajar/mahasiswa',
            self::Other => 'Lainnya',
        };
    }
}
