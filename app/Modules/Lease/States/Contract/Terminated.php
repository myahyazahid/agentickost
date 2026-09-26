<?php

namespace App\Modules\Lease\States\Contract;

use Filament\Support\Icons\Heroicon;

final class Terminated extends ContractState
{
    /** @var string */
    public static $name = 'terminated';

    public function getLabel(): string
    {
        return 'Diputus';
    }

    public function getColor(): string
    {
        return 'danger';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedXCircle;
    }
}
