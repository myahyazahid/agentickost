<?php

namespace App\Modules\Lease\States\Contract;

use Filament\Support\Icons\Heroicon;

final class Draft extends ContractState
{
    /** @var string */
    public static $name = 'draft';

    public function getLabel(): string
    {
        return 'Draf';
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedPencilSquare;
    }
}
