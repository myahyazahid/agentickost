<?php

namespace App\Modules\Property\States\Room;

use App\Modules\Property\Models\Room;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Room status (PRD §9.1). Every status has a label and an icon so it is never
 * told apart by color alone (NFR-UX-03).
 *
 * @extends State<Room>
 */
abstract class RoomState extends State implements HasColor, HasIcon, HasLabel
{
    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    abstract public function getIcon(): Heroicon;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Available::class)
            ->allowTransition(Available::class, Held::class)
            ->allowTransition(Held::class, Available::class)
            ->allowTransition(Held::class, Booked::class)
            ->allowTransition(Available::class, Booked::class)
            ->allowTransition(Booked::class, Available::class)
            ->allowTransition(Booked::class, Occupied::class)
            ->allowTransition(Available::class, Occupied::class)
            ->allowTransition(Occupied::class, Vacating::class)
            ->allowTransition(Vacating::class, Occupied::class)
            ->allowTransition(Vacating::class, Maintenance::class)
            ->allowTransition(Occupied::class, Maintenance::class)
            ->allowTransition(Available::class, Maintenance::class)
            ->allowTransition(Maintenance::class, Available::class)
            // PRD §8.9: after check-out a room may go straight back to available.
            ->allowTransition(Occupied::class, Available::class)
            ->allowTransition(Vacating::class, Available::class);
    }
}
