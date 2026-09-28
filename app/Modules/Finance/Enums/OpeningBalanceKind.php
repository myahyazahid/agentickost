<?php

namespace App\Modules\Finance\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What an opening balance line carries in (FR-ONB-04). Arrears, deposit,
 * and credit belong to a contract; cash belongs to a cash or bank account.
 */
enum OpeningBalanceKind: string implements HasLabel
{
    case Receivable = 'receivable';
    case Deposit = 'deposit';
    case Credit = 'credit';
    case Cash = 'cash';

    public function getLabel(): string
    {
        return match ($this) {
            self::Receivable => 'Tunggakan',
            self::Deposit => 'Deposit dipegang',
            self::Credit => 'Saldo kredit',
            self::Cash => 'Kas dan bank',
        };
    }

    public function isPerContract(): bool
    {
        return $this !== self::Cash;
    }
}
