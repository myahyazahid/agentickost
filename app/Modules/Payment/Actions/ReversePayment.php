<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Enums\CreditTransactionType;
use App\Modules\Payment\Events\PaymentReversed;
use App\Modules\Payment\Models\CreditTransaction;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\States\Payment\Reversed;
use App\Modules\Payment\States\Payment\Verified;
use App\Modules\Payment\Support\AllocationWriter;
use App\Modules\Payment\Support\CreditLedger;
use App\Support\Actions\Action;
use App\Support\States\StateTransition;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a verified payment that was wrong, such as a transfer that bounced
 * or a double entry (PRD §8.10). The payment stays on record as reversed;
 * the invoices it paid owe that money again, and credit it created is taken
 * back, from invoices it already paid if needed. A deposit received through
 * it must still be held.
 */
final class ReversePayment extends Action
{
    public function __construct(
        private readonly AllocationWriter $writer,
        private readonly CreditLedger $credit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Payment $payment, array $input): Payment
    {
        $this->authorize('reverse', $payment);

        $data = $this->validate($input, [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return $this->transaction(function () use ($payment, $data): Payment {
            $contract = Contract::query()->whereKey($payment->contract_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $payment->status->equals(Verified::class)) {
                throw ValidationException::withMessages(['reason' => 'Hanya pembayaran terverifikasi yang bisa dibalik.']);
            }

            $allocations = $payment->allocations()->active()->orderByDesc('id')->get();
            $invoices = Invoice::query()
                ->whereIn('id', $allocations->pluck('invoice_id')->unique())
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($allocations as $allocation) {
                $this->writer->reverse($contract, $allocation, $invoices->get($allocation->invoice_id) ?? throw ValidationException::withMessages(['reason' => 'Tagihan pembayaran ini tidak ditemukan.']));
            }

            $credited = (int) CreditTransaction::query()->where('payment_id', $payment->id)->sum('amount');
            $reclaimed = false;

            if ($credited > 0) {
                $reclaimed = CreditLedger::balance($contract->id) < $credited;
                $this->credit->reclaim($contract, $credited);
                $this->credit->record($contract, CreditTransactionType::Reversal, -$credited, ['payment_id' => $payment->id]);
            }

            $payment->reversed_at = now();
            $payment->reversal_reason = $data['reason'];
            StateTransition::to($payment->status, Reversed::class, 'reason');

            if ($reclaimed) {
                $this->credit->applyToOpenInvoices($contract);
            }

            PaymentReversed::dispatch($payment);

            return $payment;
        });
    }
}
