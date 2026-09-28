<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\Draft;
use App\Modules\Payment\Enums\PaymentChannel;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Support\Allocations;
use App\Modules\Payment\Support\PaymentVerification;
use App\Modules\Property\Models\Property;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Records a transfer or cash payment for a contract (FR-PAY-01), with proof
 * of transfer if there is one (FR-PAY-02).
 *
 * Staff who may verify payments get it verified and allocated at once. So
 * does cash the recorder received personally: it is in their hands and
 * counts toward their cash on hand (FR-PAY-07). Anything else waits in the
 * verification queue (FR-PAY-03).
 */
final class RecordPayment extends Action
{
    public function __construct(
        private readonly AttachmentSync $attachments,
        private readonly ActorContext $actors,
        private readonly PaymentVerification $verification,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, array $input): Payment
    {
        $property = $contract->property()->firstOrFail();

        $this->authorize('recordFor', [Payment::class, $property]);

        $data = $this->validate($input, [
            'method' => ['required', Rule::in(array_keys(PaymentMethod::manualOptions()))],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'bank_account_id' => ['nullable', 'required_if:method,transfer', 'string'],
            'received_by_user_id' => ['nullable', 'required_if:method,cash', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
            'proofs' => ['nullable', 'array', 'max:5'],
            'proofs.*' => ['string'],
            ...Allocations::rules(),
        ]);

        if ($contract->status->equals(Draft::class)) {
            throw ValidationException::withMessages(['contract_id' => 'Kontrak ini masih draf. Aktifkan dulu sebelum mencatat pembayaran.']);
        }

        if (CarbonImmutable::parse($data['paid_at'])->isFuture()) {
            throw ValidationException::withMessages(['paid_at' => 'Waktu bayar tidak boleh di masa depan.']);
        }

        $method = PaymentMethod::from($data['method']);
        $bankAccountId = $method === PaymentMethod::Transfer ? $this->bankAccount($property, $data['bank_account_id'])->id : null;
        $receiver = $method === PaymentMethod::Cash ? $this->receiver($property, $data['received_by_user_id']) : null;
        $verifyNow = $this->verifiesNow($property, $receiver);

        return $this->transaction(function () use ($contract, $property, $data, $method, $bankAccountId, $receiver, $verifyNow): Payment {
            $contract = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            $payment = Payment::create([
                'property_id' => $property->id,
                'contract_id' => $contract->id,
                'payer_id' => $contract->payer_id,
                'method' => $method,
                'channel' => PaymentChannel::Manual,
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'],
                'bank_account_id' => $bankAccountId,
                'received_by_user_id' => $receiver?->id,
                'reference' => $data['reference'] ?? null,
            ]);

            $this->attachments->sync($payment, AttachmentCollection::PaymentProof, $data['proofs'] ?? [], 'proofs');

            if ($verifyNow) {
                $this->verification->verify($payment, $contract, Allocations::targets($data));
            }

            return $payment;
        });
    }

    private function bankAccount(Property $property, mixed $id): BankAccount
    {
        $account = BankAccount::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('property_id')->orWhere('property_id', $property->id))
            ->first();

        return $account ?? throw ValidationException::withMessages(['bank_account_id' => 'Rekening tujuan tidak tersedia untuk properti ini.']);
    }

    /**
     * Cash is received by an active owner or by staff assigned to the
     * property. Staff who cannot verify only record cash they received.
     */
    private function receiver(Property $property, mixed $id): User
    {
        $receiver = User::query()->whereKey($id)->where('is_active', true)->first();

        if ($receiver === null || ! $property->isAccessibleBy($receiver)) {
            throw ValidationException::withMessages(['received_by_user_id' => 'Penerima uang harus staf aktif di properti ini.']);
        }

        $actor = $this->actors->current();

        if ($actor->type === ActorType::User && $receiver->id !== $actor->id && ! $this->canVerify($property)) {
            throw ValidationException::withMessages(['received_by_user_id' => 'Catat hanya uang tunai yang Anda terima sendiri.']);
        }

        return $receiver;
    }

    private function verifiesNow(Property $property, ?User $receiver): bool
    {
        $actor = $this->actors->current();

        return $this->canVerify($property) || ($receiver !== null && $actor->type === ActorType::User && $receiver->id === $actor->id);
    }

    private function canVerify(Property $property): bool
    {
        $actor = $this->actors->current();

        if ($actor->type === ActorType::System) {
            return true;
        }

        $user = $actor->user ?? User::query()->find($actor->id);

        return $user !== null && Gate::forUser($user)->allows('verifyIn', [Payment::class, $property]);
    }
}
