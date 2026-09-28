<?php

namespace App\Modules\Payment\States\Payment;

use Filament\Support\Icons\Heroicon;

final class Reversed extends PaymentState
{
    /** @var string */
    public static $name = 'reversed';

    public function getLabel(): string
    {
        return 'Dibalik';
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedArrowUturnLeft;
    }
}
