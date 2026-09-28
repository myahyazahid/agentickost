<?php

namespace App\Modules\Finance\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Entries of a contract's deposit ledger (FR-DEP-01). Positive amounts add to
 * the deposit held, negative ones take from it.
 */
enum DepositTransactionType: string implements HasLabel
{
    case Received = 'received';
    case Deducted = 'deducted';
    case Refunded = 'refunded';
    case Transferred = 'transferred';
    case Opening = 'opening';
    case Reversal = 'reversal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => 'Diterima',
            self::Deducted => 'Dipotong',
            self::Refunded => 'Dikembalikan',
            self::Transferred => 'Dipindahkan',
            self::Opening => 'Saldo awal',
            self::Reversal => 'Pembalikan',
        };
    }
}
