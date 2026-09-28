<?php

namespace App\Modules\Payment\States\Payment;

use Filament\Support\Icons\Heroicon;

final class Pending extends PaymentState
{
    /** @var string */
    public static $name = 'pending';

    public function getLabel(): string
    {
        return 'Menunggu verifikasi';
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
