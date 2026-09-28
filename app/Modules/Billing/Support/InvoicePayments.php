<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\Invoice;
use LogicException;

/**
 * How other modules move money onto and off an issued invoice. The Payment
 * module calls this instead of writing the invoices table itself. Call inside
 * a transaction holding the invoice row lock.
 */
final class InvoicePayments
{
    public static function apply(Invoice $invoice, int $amount): void
    {
        if (! $invoice->status->isOpen()) {
            throw new LogicException("Tagihan {$invoice->number} tidak sedang menunggu pembayaran.");
        }

        $invoice->paid_amount += $amount;

        InvoiceBalance::sync($invoice);
    }

    public static function release(Invoice $invoice, int $amount): void
    {
        if ($amount > $invoice->paid_amount) {
            throw new LogicException("Pembayaran tagihan {$invoice->number} lebih kecil dari yang dibatalkan.");
        }

        $invoice->paid_amount -= $amount;

        InvoiceBalance::sync($invoice);
    }
}
