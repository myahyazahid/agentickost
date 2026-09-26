<?php

namespace App\Modules\Property\States\Room;

use Filament\Support\Icons\Heroicon;

final class Held extends RoomState
{
    /** @var string */
    public static $name = 'held';

    public function getLabel(): string
    {
        return 'Ditahan';
    }

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedClock;
    }
}
