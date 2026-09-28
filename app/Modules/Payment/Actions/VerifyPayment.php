<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\States\Payment\Pending;
use App\Modules\Payment\Support\Allocations;
use App\Modules\Payment\Support\PaymentVerification;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Accepts a pending payment after checking the proof (FR-PAY-03) and
 * allocates it, automatically or to the invoices chosen (FR-PAY-04).
 */
final class VerifyPayment extends Action
{
    public function __construct(private readonly PaymentVerification $verification) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Payment $payment, array $input = []): Payment
    {
        $this->authorize('verify', $payment);

        $data = $this->validate($input, Allocations::rules());

        return $this->transaction(function () use ($payment, $data): Payment {
            $contract = Contract::query()->whereKey($payment->contract_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $payment->status->equals(Pending::class)) {
                throw ValidationException::withMessages(['amount' => 'Pembayaran ini sudah diperiksa.']);
            }

            $this->verification->verify($payment, $contract, Allocations::targets($data));

            return $payment;
        });
    }
}
