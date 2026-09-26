<?php

namespace App\Modules\Property\States\Room;

use Filament\Support\Icons\Heroicon;

final class Occupied extends RoomState
{
    /** @var string */
    public static $name = 'occupied';

    public function getLabel(): string
    {
        return 'Terisi';
    }

    public function getColor(): string
    {
        return 'primary';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedUser;
    }
}
