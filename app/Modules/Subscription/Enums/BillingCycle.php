<?php

namespace App\Modules\Subscription\Enums;

use Filament\Support\Contracts\HasLabel;

enum BillingCycle: string implements HasLabel
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => 'Bulanan',
            self::Yearly => 'Tahunan',
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
        };
    }
}
