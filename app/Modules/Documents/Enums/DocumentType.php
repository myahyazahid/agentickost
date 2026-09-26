<?php

namespace App\Modules\Documents\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Documents numbered per tenant (FR-BIL-07, schema §3.7).
 *
 * Format tokens: {YYYY}, {YY}, {MM}, {PROP} (property code), {SEQ:n}
 * (sequence padded to n digits).
 */
enum DocumentType: string implements HasLabel
{
    case Invoice = 'invoice';
    case Receipt = 'receipt';
    case CreditNote = 'credit_note';
    case Contract = 'contract';
    case Journal = 'journal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Invoice => 'Tagihan',
            self::Receipt => 'Kuitansi',
            self::CreditNote => 'Nota kredit',
            self::Contract => 'Kontrak',
            self::Journal => 'Jurnal',
        };
    }

    public function defaultFormat(): string
    {
        return match ($this) {
            self::Invoice => 'INV/{YYYY}/{MM}/{SEQ:4}',
            self::Receipt => 'KWT/{YYYY}/{MM}/{SEQ:4}',
            self::CreditNote => 'NK/{YYYY}/{SEQ:4}',
            self::Contract => 'KTR/{YYYY}/{SEQ:4}',
            self::Journal => 'JU/{YYYY}/{MM}/{SEQ:5}',
        };
    }

    public function defaultResetPeriod(): ResetPeriod
    {
        return match ($this) {
            self::Invoice, self::Receipt, self::Journal => ResetPeriod::Monthly,
            self::CreditNote, self::Contract => ResetPeriod::Yearly,
        };
    }
}
