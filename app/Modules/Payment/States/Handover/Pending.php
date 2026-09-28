<?php

namespace App\Modules\Payment\States\Handover;

use Filament\Support\Icons\Heroicon;

final class Pending extends HandoverState
{
    /** @var string */
    public static $name = 'pending';

    public function getLabel(): string
    {
        return 'Menunggu konfirmasi';
    }

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedClock;
    }
}
