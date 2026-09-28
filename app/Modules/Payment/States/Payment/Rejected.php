<?php

namespace App\Modules\Payment\States\Payment;

use Filament\Support\Icons\Heroicon;

final class Rejected extends PaymentState
{
    /** @var string */
    public static $name = 'rejected';

    public function getLabel(): string
    {
        return 'Ditolak';
    }

    public function getColor(): string
    {
        return 'danger';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedXCircle;
    }
}
