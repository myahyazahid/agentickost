<?php

namespace App\Modules\Maintenance\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Every priority has a label and an icon, never color alone (NFR-UX-03).
 */
enum TicketPriority: string implements HasColor, HasIcon, HasLabel
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Normal => 'Biasa',
            self::High => 'Tinggi',
            self::Urgent => 'Darurat',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low, self::Normal => 'gray',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Low => Heroicon::OutlinedArrowDown,
            self::Normal => Heroicon::OutlinedMinus,
            self::High => Heroicon::OutlinedArrowUp,
            self::Urgent => Heroicon::OutlinedExclamationTriangle,
        };
    }
}
