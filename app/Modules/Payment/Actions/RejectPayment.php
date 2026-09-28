<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Payment\Events\PaymentRejected;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\States\Payment\Pending;
use App\Modules\Payment\States\Payment\Rejected;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\States\StateTransition;
use Illuminate\Validation\ValidationException;

/**
 * Turns down a pending payment, for example when the transfer never arrived
 * (FR-PAY-03). The reason is kept so the payer can be told why.
 */
final class RejectPayment extends Action
{
    public function __construct(private readonly ActorContext $actors) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Payment $payment, array $input): Payment
    {
        $this->authorize('verify', $payment);

        $data = $this->validate($input, [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return $this->transaction(function () use ($payment, $data): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $payment->status->equals(Pending::class)) {
                throw ValidationException::withMessages(['reason' => 'Pembayaran ini sudah diperiksa.']);
            }

            $actor = $this->actors->current();

            $payment->rejection_reason = $data['reason'];
            $payment->verified_by_type = $actor->type;
            $payment->verified_by_id = $actor->id;
            $payment->verified_at = now();
            StateTransition::to($payment->status, Rejected::class, 'reason');

            PaymentRejected::dispatch($payment);

            return $payment;
        });
    }
}
