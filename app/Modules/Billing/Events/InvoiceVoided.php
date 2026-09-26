<?php

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An issued invoice without payments was cancelled.
 */
final class InvoiceVoided
{
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
