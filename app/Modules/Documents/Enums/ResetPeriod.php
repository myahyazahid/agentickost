<?php

namespace App\Modules\Documents\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

enum ResetPeriod: string implements HasLabel
{
    case Never = 'never';
    case Yearly = 'yearly';
    case Monthly = 'monthly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Never => 'Tidak pernah diulang',
            self::Yearly => 'Diulang dari 1 setiap tahun',
            self::Monthly => 'Diulang dari 1 setiap bulan',
        };
    }

    /**
     * The key of the numbering period a date falls in, e.g. "2026-09".
     */
    public function keyFor(CarbonInterface $date): string
    {
        return match ($this) {
            self::Never => 'all',
            self::Yearly => $date->format('Y'),
            self::Monthly => $date->format('Y-m'),
        };
    }
}
