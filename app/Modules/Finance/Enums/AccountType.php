<?php

namespace App\Modules\Finance\Enums;

use Filament\Support\Contracts\HasLabel;

enum AccountType: string implements HasLabel
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function getLabel(): string
    {
        return match ($this) {
            self::Asset => 'Aset',
            self::Liability => 'Kewajiban',
            self::Equity => 'Ekuitas',
            self::Revenue => 'Pendapatan',
            self::Expense => 'Beban',
        };
    }

    /**
     * Asset and expense balances grow on the debit side; the rest on credit.
     */
    public function isDebitNormal(): bool
    {
        return in_array($this, [self::Asset, self::Expense], true);
    }
}
