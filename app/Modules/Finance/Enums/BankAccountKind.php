<?php

namespace App\Modules\Finance\Enums;

use Filament\Support\Contracts\HasLabel;

enum BankAccountKind: string implements HasLabel
{
    case Bank = 'bank';
    case Ewallet = 'ewallet';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bank => 'Rekening bank',
            self::Ewallet => 'E-wallet',
        };
    }
}
