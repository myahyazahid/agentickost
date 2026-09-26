<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Draft;
use App\Modules\Billing\Support\AdhocItems;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

final class UpdateDraftInvoice extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Invoice $invoice, array $input): Invoice
    {
        $this->authorize('update', $invoice);

        if (! $invoice->status->equals(Draft::class)) {
            throw ValidationException::withMessages(['status' => 'Tagihan yang sudah terbit tidak dapat diubah. Pakai void atau nota kredit.']);
        }

        $data = $this->validate($input, [
            'due_date' => ['required', 'date'],
            ...AdhocItems::rules(),
        ]);

        return $this->transaction(function () use ($invoice, $data): Invoice {
            $invoice->update(['due_date' => $data['due_date']]);
            AdhocItems::replace($invoice, $data['items']);

            return $invoice;
        });
    }
}
