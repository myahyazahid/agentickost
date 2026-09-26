<?php

namespace App\Modules\Lease\States\Contract;

use Filament\Support\Icons\Heroicon;

final class Notice extends ContractState
{
    /** @var string */
    public static $name = 'notice';

    public function getLabel(): string
    {
        return 'Akan keluar';
    }

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedArrowRightStartOnRectangle;
    }

    public function isRunning(): bool
    {
        return true;
    }
}
