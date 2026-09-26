<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Draft;
use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Billing\States\Invoice\Issued;
use App\Modules\Billing\States\Invoice\Paid;
use App\Modules\Billing\States\Invoice\Partial;
use App\Modules\Billing\States\Invoice\Voided;

/**
 * Keeps an issued invoice's status in line with its amounts: settled when
 * nothing is left, partial once some money came in, otherwise unpaid.
 */
final class InvoiceBalance
{
    public static function outstanding(Invoice $invoice): int
    {
        return $invoice->items_total_amount + $invoice->penalty_amount - $invoice->paid_amount - $invoice->credited_amount;
    }

    public static function sync(Invoice $invoice): void
    {
        if ($invoice->status->equals(Draft::class, Voided::class)) {
            $invoice->save();

            return;
        }

        /** @var class-string<InvoiceState> $target */
        $target = match (true) {
            self::outstanding($invoice) <= 0 => Paid::class,
            $invoice->paid_amount > 0 => Partial::class,
            default => Issued::class,
        };

        if ($invoice->status->equals($target)) {
            $invoice->save();

            return;
        }

        $invoice->status->transitionTo($target);
    }
}
