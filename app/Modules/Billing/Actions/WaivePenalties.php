<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Access\Audit\AuditLogger;
use App\Modules\Billing\Events\PenaltyWaived;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PenaltyAccrual;
use App\Modules\Billing\Support\InvoiceBalance;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use Illuminate\Validation\ValidationException;

/**
 * Waives the penalties charged on an invoice so far, with a reason that goes
 * into the audit log (PRD §8.4). A daily penalty keeps accruing for later
 * days while the invoice stays unpaid.
 */
final class WaivePenalties extends Action
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return list<PenaltyAccrual>
     */
    public function handle(Invoice $invoice, array $input): array
    {
        $this->authorize('waivePenalty', $invoice);

        $data = $this->validate($input, [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return $this->transaction(function () use ($invoice, $data): array {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $penalties = array_values($invoice->penalties()->whereNull('waived_at')->orderBy('accrued_on')->get()->all());

            if ($penalties === [] || ! $invoice->status->isOpen()) {
                throw ValidationException::withMessages(['reason' => 'Tidak ada denda aktif di tagihan ini.']);
            }

            $actor = $this->actors->current();
            $before = $invoice->penalty_amount;

            foreach ($penalties as $penalty) {
                $penalty->waived_at = now();
                $penalty->waived_by = $actor->type === ActorType::User ? $actor->id : null;
                $penalty->waive_reason = $data['reason'];
                $penalty->save();

                $invoice->penalty_amount -= $penalty->amount;
            }

            InvoiceBalance::sync($invoice);

            $this->audit->record(
                'penalty.waived',
                $invoice,
                ['penalty_amount' => $before],
                ['penalty_amount' => $invoice->penalty_amount],
                $data['reason'],
            );

            foreach ($penalties as $penalty) {
                PenaltyWaived::dispatch($penalty);
            }

            return $penalties;
        });
    }
}
