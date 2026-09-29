<?php

namespace App\Modules\Finance\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Sections of the cash flow statement (FR-ACC-06).
 */
enum CashActivity: string implements HasLabel
{
    case Operating = 'operating';
    case Investing = 'investing';
    case Financing = 'financing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Operating => 'Arus kas dari operasional',
            self::Investing => 'Arus kas dari investasi',
            self::Financing => 'Arus kas dari pendanaan',
        };
    }
}
