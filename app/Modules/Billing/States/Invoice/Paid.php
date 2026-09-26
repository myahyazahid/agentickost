<?php

namespace App\Modules\Billing\States\Invoice;

use Filament\Support\Icons\Heroicon;

final class Paid extends InvoiceState
{
    /** @var string */
    public static $name = 'paid';

    public function getLabel(): string
    {
        return 'Lunas';
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
