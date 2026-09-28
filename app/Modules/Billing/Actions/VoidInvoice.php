<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Events\InvoiceVoided;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\MeterReading;
use App\Modules\Billing\States\Invoice\Issued;
use App\Modules\Billing\States\Invoice\Voided;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Support\BillingCursor;
use App\Support\Actions\Action;
use App\Support\States\StateTransition;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Cancels an issued invoice that has no payment yet (PRD §8.10). Its
 * penalties are waived with it and its meter readings can be billed again.
 * When it is the latest rent invoice of the contract, that period is issued
 * again on the next run, so a corrected rent or reading is picked up.
 */
final class VoidInvoice extends Action
{
    public function __construct(private readonly BillingCursor $cursor) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Invoice $invoice, array $input): Invoice
    {
        $this->authorize('void', $invoice);

        $data = $this->validate($input, [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return $this->transaction(function () use ($invoice, $data): Invoice {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $invoice->status->equals(Issued::class) || $invoice->paid_amount > 0) {
                throw ValidationException::withMessages([
                    'reason' => 'Hanya tagihan tanpa pembayaran yang bisa dibatalkan. Untuk tagihan yang sudah dibayar, buat nota kredit.',
                ]);
            }

            if ($invoice->type === InvoiceType::Opening) {
                throw ValidationException::withMessages([
                    'reason' => 'Tunggakan saldo awal tidak bisa dibatalkan. Kurangi dengan nota kredit.',
                ]);
            }

            if ($invoice->credited_amount > 0) {
                throw ValidationException::withMessages([
                    'reason' => 'Tagihan ini sudah punya nota kredit. Koreksi berikutnya juga lewat nota kredit.',
                ]);
            }

            $now = now();

            $invoice->penalties()->whereNull('waived_at')->get()->each(function ($penalty) use ($now, $data): void {
                $penalty->waived_at = $now;
                $penalty->waive_reason = 'Tagihan dibatalkan: '.$data['reason'];
                $penalty->save();
            });

            MeterReading::query()
                ->whereIn('invoice_item_id', $invoice->items()->select('id'))
                ->update(['invoice_item_id' => null]);

            if ($invoice->type === InvoiceType::Rent) {
                $this->rewindCursor($invoice);
            }

            $invoice->penalty_amount = 0;
            $invoice->generation_key = null;
            $invoice->voided_at = $now;
            $invoice->void_reason = $data['reason'];
            StateTransition::to($invoice->status, Voided::class, 'reason');

            InvoiceVoided::dispatch($invoice);

            return $invoice;
        });
    }

    private function rewindCursor(Invoice $invoice): void
    {
        $contract = Contract::query()->whereKey($invoice->contract_id)->lockForUpdate()->first();

        if ($contract === null || $invoice->period_start === null || $invoice->period_end === null) {
            return;
        }

        $afterPeriod = CarbonImmutable::parse($invoice->period_end->toDateString())->addDay();

        if ($this->cursor->nextPeriodStart($contract)->equalTo($afterPeriod)) {
            $this->cursor->moveTo($contract, CarbonImmutable::parse($invoice->period_start->toDateString()));
        }
    }
}
