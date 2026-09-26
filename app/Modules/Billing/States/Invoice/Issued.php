<?php

namespace App\Modules\Billing\States\Invoice;

use Filament\Support\Icons\Heroicon;

final class Issued extends InvoiceState
{
    /** @var string */
    public static $name = 'issued';

    public function getLabel(): string
    {
        return 'Belum dibayar';
    }

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedClock;
    }

    public function isOpen(): bool
    {
        return true;
    }
}
