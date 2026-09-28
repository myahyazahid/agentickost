<?php

namespace App\Modules\Payment\States\Handover;

use Filament\Support\Icons\Heroicon;

final class Confirmed extends HandoverState
{
    /** @var string */
    public static $name = 'confirmed';

    public function getLabel(): string
    {
        return 'Diterima';
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
