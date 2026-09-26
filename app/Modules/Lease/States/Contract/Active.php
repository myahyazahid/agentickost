<?php

namespace App\Modules\Lease\States\Contract;

use Filament\Support\Icons\Heroicon;

final class Active extends ContractState
{
    /** @var string */
    public static $name = 'active';

    public function getLabel(): string
    {
        return 'Aktif';
    }

    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedCheckCircle;
    }

    public function isRunning(): bool
    {
        return true;
    }
}
