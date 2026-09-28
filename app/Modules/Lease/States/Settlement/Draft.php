<?php

namespace App\Modules\Lease\States\Settlement;

use Filament\Support\Icons\Heroicon;

final class Draft extends SettlementState
{
    /** @var string */
    public static $name = 'draft';

    public function getLabel(): string
    {
        return 'Draf';
    }

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedPencilSquare;
    }
}
