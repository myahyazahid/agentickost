<?php

namespace App\Modules\Maintenance\Enums;

use Filament\Support\Contracts\HasLabel;

enum TicketCategory: string implements HasLabel
{
    case Electrical = 'electrical';
    case Plumbing = 'plumbing';
    case Ac = 'ac';
    case Furniture = 'furniture';
    case Cleaning = 'cleaning';
    case Internet = 'internet';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Electrical => 'Listrik',
            self::Plumbing => 'Air dan saluran',
            self::Ac => 'AC atau kipas',
            self::Furniture => 'Perabot',
            self::Cleaning => 'Kebersihan',
            self::Internet => 'Internet',
            self::Other => 'Lainnya',
        };
    }
}
