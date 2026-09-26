<?php

namespace App\Modules\Billing\Engine;

/**
 * Rounds an invoice total to the property's unit, such as Rp1.000 (PRD §8.1).
 * The difference goes on the invoice as its own "rounding" line.
 */
final class TotalRounding
{
    public static function difference(int $total, int $unit): int
    {
        if ($unit <= 1) {
            return 0;
        }

        return Proration::divideRounded($total, $unit) * $unit - $total;
    }
}
