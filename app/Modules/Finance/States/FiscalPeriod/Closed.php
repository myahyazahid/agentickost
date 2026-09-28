<?php

namespace App\Modules\Finance\States\FiscalPeriod;

use Filament\Support\Icons\Heroicon;

final class Closed extends FiscalPeriodState
{
    /** @var string */
    public static $name = 'closed';

    public function getLabel(): string
    {
        return 'Ditutup';
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedLockClosed;
    }
}
