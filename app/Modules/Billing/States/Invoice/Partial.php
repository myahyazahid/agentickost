<?php

namespace App\Modules\Billing\States\Invoice;

use Filament\Support\Icons\Heroicon;

final class Partial extends InvoiceState
{
    /** @var string */
    public static $name = 'partial';

    public function getLabel(): string
    {
        return 'Dibayar sebagian';
    }

    public function getColor(): string
    {
        return 'info';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedAdjustmentsHorizontal;
    }

    public function isOpen(): bool
    {
        return true;
    }
}
