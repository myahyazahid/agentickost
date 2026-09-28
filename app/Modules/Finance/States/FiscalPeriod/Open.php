<?php

namespace App\Modules\Finance\States\FiscalPeriod;

use Filament\Support\Icons\Heroicon;

final class Open extends FiscalPeriodState
{
    /** @var string */
    public static $name = 'open';

    public function getLabel(): string
    {
        return 'Terbuka';
    }

    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedLockOpen;
    }
}
