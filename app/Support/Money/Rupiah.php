<?php

namespace App\Support\Money;

/**
 * Amounts are whole rupiah stored as integers (PRD §8.1).
 */
final class Rupiah
{
    /**
     * Format as "Rp1.500.000", or "-Rp1.500.000" for negative amounts.
     */
    public static function format(int $amount): string
    {
        $formatted = 'Rp'.number_format(abs($amount), 0, ',', '.');

        return $amount < 0 ? '-'.$formatted : $formatted;
    }
}
