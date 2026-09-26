<?php

namespace App\Modules\Billing\States\Invoice;

use Filament\Support\Icons\Heroicon;

final class Draft extends InvoiceState
{
    /** @var string */
    public static $name = 'draft';

    public function getLabel(): string
    {
        return 'Draf';
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedPencilSquare;
    }
}
