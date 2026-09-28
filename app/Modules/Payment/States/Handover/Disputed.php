<?php

namespace App\Modules\Payment\States\Handover;

use Filament\Support\Icons\Heroicon;

final class Disputed extends HandoverState
{
    /** @var string */
    public static $name = 'disputed';

    public function getLabel(): string
    {
        return 'Dipersoalkan';
    }

    public function getColor(): string
    {
        return 'danger';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedExclamationTriangle;
    }
}
