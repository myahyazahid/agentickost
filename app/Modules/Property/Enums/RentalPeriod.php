<?php

namespace App\Modules\Property\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Rental periods a price or contract can use (FR-KMR-01). All are supported
 * for contracts, see docs/adr/0008-keputusan-mvp.md.
 */
enum RentalPeriod: string implements HasLabel
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => 'Harian',
            self::Weekly => 'Mingguan',
            self::Monthly => 'Bulanan',
            self::Quarterly => '3 bulanan',
            self::Semiannual => '6 bulanan',
            self::Yearly => 'Tahunan',
        };
    }

    /**
     * Length in months for month-based periods, null for daily and weekly.
     */
    public function months(): ?int
    {
        return match ($this) {
            self::Daily, self::Weekly => null,
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Semiannual => 6,
            self::Yearly => 12,
        };
    }

    /**
     * Length in days for day-based periods, null for month-based ones.
     */
    public function days(): ?int
    {
        return match ($this) {
            self::Daily => 1,
            self::Weekly => 7,
            default => null,
        };
    }

    public function isMonthBased(): bool
    {
        return $this->months() !== null;
    }
}
