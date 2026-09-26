<?php

namespace App\Modules\Property\States\Room;

use Filament\Support\Icons\Heroicon;

final class Available extends RoomState
{
    /** @var string */
    public static $name = 'available';

    public function getLabel(): string
    {
        return 'Tersedia';
    }

    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedCheckCircle;
    }
}
