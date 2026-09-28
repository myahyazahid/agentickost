<?php

namespace App\Modules\Payment\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Transfer = 'transfer';
    case Cash = 'cash';
    case Gateway = 'gateway';

    public function getLabel(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer',
            self::Cash => 'Tunai',
            self::Gateway => 'Payment gateway',
        };
    }

    /**
     * Methods staff record by hand (FR-PAY-01). Gateway payments arrive by
     * webhook (M1.5.2).
     *
     * @return array<string, string>
     */
    public static function manualOptions(): array
    {
        return [self::Transfer->value => self::Transfer->getLabel(), self::Cash->value => self::Cash->getLabel()];
    }
}
