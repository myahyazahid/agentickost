<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Support\CreditLedger;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Uses a contract's credit balance on its open invoices now, oldest first,
 * instead of waiting for the next invoice (FR-PAY-05). Returns the amount
 * used.
 */
final class ApplyCredit extends Action
{
    public function __construct(private readonly CreditLedger $credit) {}

    public function handle(Contract $contract): int
    {
        $this->authorize('applyCreditIn', [Payment::class, $contract->property()->firstOrFail()]);

        return $this->transaction(function () use ($contract): int {
            $contract = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            $applied = $this->credit->applyToOpenInvoices($contract);

            if ($applied === 0) {
                throw ValidationException::withMessages(['credit' => 'Tidak ada saldo kredit atau tagihan yang menunggu pembayaran.']);
            }

            return $applied;
        });
    }
}
