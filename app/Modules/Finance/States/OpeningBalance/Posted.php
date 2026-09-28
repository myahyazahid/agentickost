<?php

namespace App\Modules\Finance\States\OpeningBalance;

use Filament\Support\Icons\Heroicon;

final class Posted extends OpeningBalanceState
{
    /** @var string */
    public static $name = 'posted';

    public function getLabel(): string
    {
        return 'Diposting';
    }

    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedCheckCircle;
    }
}
