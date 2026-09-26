<?php

namespace App\Modules\Property\States\Room;

use Filament\Support\Icons\Heroicon;

final class Vacating extends RoomState
{
    /** @var string */
    public static $name = 'vacating';

    public function getLabel(): string
    {
        return 'Akan kosong';
    }

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedArrowRightStartOnRectangle;
    }
}
