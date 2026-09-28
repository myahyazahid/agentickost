<?php

namespace Tests\Support;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Finance\Actions\CreateBankAccount;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Actions\RecordPayment;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Tenancy\Support\TenantStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builders for payment tests. Call inside a tenant context with an acting
 * user (see loginAs()). Tests that attach proofs call Storage::fake().
 */
final class PaymentScenario
{
    public static function bankAccount(): BankAccount
    {
        return BankAccount::query()->first() ?? app(CreateBankAccount::class)->handle([
            'kind' => 'bank',
            'provider_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Siti Aminah',
            'is_default' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function transfer(Contract $contract, int $amount, array $overrides = []): Payment
    {
        return app(RecordPayment::class)->handle($contract, [
            'method' => 'transfer',
            'amount' => $amount,
            'paid_at' => now()->toDateTimeString(),
            'bank_account_id' => self::bankAccount()->id,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function cash(Contract $contract, int $amount, User $receiver, array $overrides = []): Payment
    {
        return app(RecordPayment::class)->handle($contract, [
            'method' => 'cash',
            'amount' => $amount,
            'paid_at' => now()->toDateTimeString(),
            'received_by_user_id' => $receiver->id,
            ...$overrides,
        ]);
    }

    /**
     * A transfer screenshot where Filament's upload field would put it.
     */
    public static function proof(): string
    {
        $path = app(TenantStorage::class)->path(AttachmentCollection::PaymentProof->directory().'/'.Str::ulid().'.jpg');
        Storage::put($path, 'jpeg-bytes');

        return $path;
    }

    /**
     * Active allocations of an invoice as [category, amount] pairs, oldest first.
     *
     * @return list<array{0: string, 1: int}>
     */
    public static function allocations(Invoice $invoice): array
    {
        return array_values(PaymentAllocation::query()
            ->active()
            ->where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->get()
            ->map(fn (PaymentAllocation $allocation): array => [$allocation->allocation_category->value, $allocation->amount])
            ->all());
    }
}
