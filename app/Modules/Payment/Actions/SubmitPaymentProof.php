<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Enums\PaymentChannel;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Enums\PaymentPermission;
use App\Modules\Payment\Filament\App\Resources\Payments\PaymentResource;
use App\Modules\Payment\Models\Payment;
use App\Modules\Property\Models\Property;
use App\Support\Actions\Action;
use App\Support\Money\Rupiah;
use Carbon\CarbonImmutable;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * A resident or payer reports a transfer with its proof from the portal
 * (FR-PRT-03). The payment waits for staff to check it against the bank
 * (FR-PAY-03); those who can verify payments at the property are told.
 */
final class SubmitPaymentProof extends Action
{
    public const MAX_AMOUNT = 1_000_000_000;

    public function __construct(private readonly AttachmentSync $attachments) {}

    /**
     * @param  array<string, mixed>  $input  amount, paid_at, bank_account_id, proofs, note
     */
    public function handle(Contract $contract, array $input): Payment
    {
        $this->authorize('submitProof', [Payment::class, $contract]);

        $data = $this->validate($input, [
            'amount' => ['required', 'integer', 'min:1', 'max:'.self::MAX_AMOUNT],
            'paid_at' => ['required', 'date'],
            'bank_account_id' => ['required', 'string'],
            'proofs' => ['required', 'array', 'min:1', 'max:3'],
            'proofs.*' => ['string'],
            'note' => ['nullable', 'string', 'max:100'],
        ]);

        if (CarbonImmutable::parse($data['paid_at'])->isFuture()) {
            throw ValidationException::withMessages(['paid_at' => 'Waktu transfer tidak boleh di masa depan.']);
        }

        $property = $contract->property()->firstOrFail();
        $account = $this->bankAccount($property, $data['bank_account_id']);

        $payment = $this->transaction(function () use ($contract, $property, $data, $account): Payment {
            $payment = Payment::create([
                'property_id' => $property->id,
                'contract_id' => $contract->id,
                'payer_id' => $contract->payer_id,
                'method' => PaymentMethod::Transfer,
                'channel' => PaymentChannel::Portal,
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'],
                'bank_account_id' => $account->id,
                'reference' => $data['note'] ?? null,
            ]);

            $this->attachments->sync($payment, AttachmentCollection::PaymentProof, $data['proofs'], 'proofs');

            return $payment;
        });

        $this->tellVerifiers($payment, $property, $contract);

        return $payment;
    }

    private function bankAccount(Property $property, mixed $id): BankAccount
    {
        $account = BankAccount::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('property_id')->orWhere('property_id', $property->id))
            ->first();

        return $account ?? throw ValidationException::withMessages(['bank_account_id' => 'Pilih rekening tujuan transfer.']);
    }

    private function tellVerifiers(Payment $payment, Property $property, Contract $contract): void
    {
        $verifiers = User::query()
            ->permission(PaymentPermission::VerifyPayments->value)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => $property->isAccessibleBy($user));

        Notification::make()
            ->info()
            // Notifications render limited HTML; names typed by residents are escaped.
            ->title(e('Bukti transfer '.Rupiah::format($payment->amount).' dari portal'))
            ->body(e("Kamar {$contract->room?->number}, {$property->name}. Periksa mutasi rekening lalu verifikasi."))
            ->actions([
                NotificationAction::make('open')
                    ->label('Periksa pembayaran')
                    ->url(PaymentResource::getUrl('view', ['record' => $payment], panel: 'app')),
            ])
            ->sendToDatabase($verifiers);
    }
}
