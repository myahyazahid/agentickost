<?php

namespace App\Modules\Finance\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Business events that post an automatic journal (PRD §8.14).
 */
enum JournalEvent: string implements HasLabel
{
    case InvoiceIssued = 'invoice_issued';
    case InvoiceVoided = 'invoice_voided';
    case PenaltyAccrued = 'penalty_accrued';
    case PenaltyWaived = 'penalty_waived';
    case CreditNoteIssued = 'credit_note_issued';
    case PaymentVerified = 'payment_verified';
    case PaymentReversed = 'payment_reversed';
    case CreditApplied = 'credit_applied';
    case CreditRefunded = 'credit_refunded';
    case AllocationReleased = 'allocation_released';
    case DepositDeducted = 'deposit_deducted';
    case DepositRefunded = 'deposit_refunded';
    case DepositTransferred = 'deposit_transferred';
    case CashHandedOver = 'cash_handed_over';
    case ExpenseRecorded = 'expense_recorded';
    case ExpenseVoided = 'expense_voided';
    case OpeningBalance = 'opening_balance';

    public function getLabel(): string
    {
        return match ($this) {
            self::InvoiceIssued => 'Tagihan terbit',
            self::InvoiceVoided => 'Tagihan dibatalkan',
            self::PenaltyAccrued => 'Denda',
            self::PenaltyWaived => 'Denda dihapus',
            self::CreditNoteIssued => 'Nota kredit',
            self::PaymentVerified => 'Pembayaran',
            self::PaymentReversed => 'Pembayaran dibalik',
            self::CreditApplied => 'Saldo kredit dipakai',
            self::CreditRefunded => 'Saldo kredit dikembalikan',
            self::AllocationReleased => 'Pembayaran dilepas dari tagihan',
            self::DepositDeducted => 'Potongan deposit',
            self::DepositRefunded => 'Deposit dikembalikan',
            self::DepositTransferred => 'Deposit dipindahkan',
            self::CashHandedOver => 'Setoran staf',
            self::ExpenseRecorded => 'Pengeluaran',
            self::ExpenseVoided => 'Pengeluaran dibatalkan',
            self::OpeningBalance => 'Saldo awal',
        };
    }
}
