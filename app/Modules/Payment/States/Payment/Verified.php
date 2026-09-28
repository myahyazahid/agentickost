<?php

namespace App\Modules\Payment\States\Payment;

use Filament\Support\Icons\Heroicon;

final class Verified extends PaymentState
{
    /** @var string */
    public static $name = 'verified';

    public function getLabel(): string
    {
        return 'Terverifikasi';
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
