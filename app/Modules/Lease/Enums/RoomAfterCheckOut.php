<?php

namespace App\Modules\Lease\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What happens to the room once check-out is settled (PRD §8.9).
 */
enum RoomAfterCheckOut: string implements HasLabel
{
    case Available = 'available';
    case Maintenance = 'maintenance';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Siap disewakan lagi',
            self::Maintenance => 'Perlu perbaikan dulu',
        };
    }
}
