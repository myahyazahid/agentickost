<?php

namespace App\Modules\Billing\States\Invoice;

use Filament\Support\Icons\Heroicon;

final class Voided extends InvoiceState
{
    /** @var string */
    public static $name = 'void';

    public function getLabel(): string
    {
        return 'Dibatalkan';
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedXCircle;
    }
}
