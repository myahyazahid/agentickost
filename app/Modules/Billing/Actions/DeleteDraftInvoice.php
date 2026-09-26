<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Draft;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Only a draft can be deleted; an issued invoice is voided instead
 * (FR-BIL-06).
 */
final class DeleteDraftInvoice extends Action
{
    public function handle(Invoice $invoice): void
    {
        $this->authorize('delete', $invoice);

        if (! $invoice->status->equals(Draft::class)) {
            throw ValidationException::withMessages(['status' => 'Tagihan yang sudah terbit tidak dapat dihapus. Batalkan dengan void.']);
        }

        $this->transaction(function () use ($invoice): void {
            $invoice->items()->get()->each->delete();
            $invoice->delete();
        });
    }
}
