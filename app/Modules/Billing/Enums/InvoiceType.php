<?php

namespace App\Modules\Billing\Enums;

use Filament\Support\Contracts\HasLabel;

enum InvoiceType: string implements HasLabel
{
    case Rent = 'rent';
    case Adhoc = 'adhoc';
    case FinalSettlement = 'final_settlement';
    case Opening = 'opening';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rent => 'Sewa',
            self::Adhoc => 'Tagihan lain',
            self::FinalSettlement => 'Penyelesaian akhir',
            self::Opening => 'Saldo awal',
        };
    }
}
