<?php

namespace App\Modules\Billing\Engine;

/**
 * nominal_prorata = harga_periode × hari_ditagih / hari_basis (PRD §8.3),
 * rounded to the nearest rupiah with halves going up (PRD §8.1). Integer
 * arithmetic only.
 */
final class Proration
{
    public static function amount(int $periodAmount, int $billedDays, int $basisDays): int
    {
        if ($billedDays >= $basisDays) {
            return $periodAmount;
        }

        return self::divideRounded($periodAmount * $billedDays, $basisDays);
    }

    /**
     * Round half up, also for negative numerators (discounts).
     */
    public static function divideRounded(int $numerator, int $denominator): int
    {
        $sign = $numerator < 0 ? -1 : 1;

        return $sign * intdiv(2 * abs($numerator) + $denominator, 2 * $denominator);
    }
}
