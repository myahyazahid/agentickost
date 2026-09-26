<?php

namespace App\Modules\Lease\States\Contract;

use Filament\Support\Icons\Heroicon;

final class Completed extends ContractState
{
    /** @var string */
    public static $name = 'completed';

    public function getLabel(): string
    {
        return 'Selesai';
    }

    public function getColor(): string
    {
        return 'info';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedCheckBadge;
    }
}
