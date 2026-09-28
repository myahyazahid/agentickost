<?php

namespace App\Modules\Lease\States\Settlement;

use Filament\Support\Icons\Heroicon;

final class Finalized extends SettlementState
{
    /** @var string */
    public static $name = 'finalized';

    public function getLabel(): string
    {
        return 'Selesai';
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
