<?php

namespace App\Modules\Property\States\Room;

use Filament\Support\Icons\Heroicon;

final class Maintenance extends RoomState
{
    /** @var string */
    public static $name = 'maintenance';

    public function getLabel(): string
    {
        return 'Perbaikan';
    }

    public function getColor(): string
    {
        return 'danger';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedWrenchScrewdriver;
    }
}
