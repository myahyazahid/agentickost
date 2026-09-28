<?php

namespace App\Modules\Payment\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Entries of a contract's credit balance (FR-PAY-05). Positive amounts add to
 * the balance, negative ones use it up.
 */
enum CreditTransactionType: string implements HasLabel
{
    case Overpayment = 'overpayment';
    case DownPayment = 'down_payment';
    case RoomMoveCredit = 'room_move_credit';
    case Applied = 'applied';
    case Refunded = 'refunded';
    case Opening = 'opening';
    case Reversal = 'reversal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Overpayment => 'Kelebihan bayar',
            self::DownPayment => 'Uang muka booking',
            self::RoomMoveCredit => 'Sisa sewa pindah kamar',
            self::Applied => 'Dipakai untuk tagihan',
            self::Refunded => 'Dikembalikan',
            self::Opening => 'Saldo awal',
            self::Reversal => 'Pembalikan',
        };
    }
}
