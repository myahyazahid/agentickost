<?php

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An invoice was issued; the credit balance may pay it and a journal is posted.
 */
final class InvoiceIssued
{
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
