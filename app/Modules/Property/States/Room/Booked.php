<?php

namespace App\Modules\Property\States\Room;

use Filament\Support\Icons\Heroicon;

final class Booked extends RoomState
{
    /** @var string */
    public static $name = 'booked';

    public function getLabel(): string
    {
        return 'Dipesan';
    }

    public function getColor(): string
    {
        return 'info';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedBookmark;
    }
}
