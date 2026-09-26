<?php

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\CreditNote;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A credit note reduced what an invoice asks for.
 */
final class CreditNoteIssued
{
    use Dispatchable;

    public function __construct(public readonly CreditNote $creditNote) {}
}
