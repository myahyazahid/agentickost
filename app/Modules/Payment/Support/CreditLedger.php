<?php

namespace App\Modules\Payment\Support;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Engine\AllocationLine;
use App\Modules\Payment\Engine\PaymentAllocator;
use App\Modules\Payment\Enums\CreditTransactionType;
use App\Modules\Payment\Events\AllocationReleased;
use App\Modules\Payment\Events\CreditApplied;
use App\Modules\Payment\Events\CreditRefunded;
use App\Modules\Payment\Models\CreditTransaction;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Support\Actors\ActorContext;
use LogicException;

/**
 * A contract's credit balance (FR-PAY-05): what was paid beyond the open
 * invoices, used for the next ones. Call inside a transaction that locked
 * the contract row first (schema §14.1).
 */
final class CreditLedger
{
    public function __construct(
        private readonly ActorContext $actors,
        private readonly AllocationWriter $writer,
    ) {}

    public static function balance(string $contractId): int
    {
        return (int) CreditTransaction::query()->where('contract_id', $contractId)->sum('amount');
    }

    /**
     * @param  array{payment_id?: string, invoice_id?: string}  $attributes
     */
    public function record(Contract $contract, CreditTransactionType $type, int $amount, array $attributes = []): CreditTransaction
    {
        $actor = $this->actors->current();

        return CreditTransaction::create([
            'contract_id' => $contract->id,
            'type' => $type,
            'amount' => $amount,
            'occurred_on' => $contract->property()->firstOrFail()->today(),
            'created_by_type' => $actor->type,
            'created_by_id' => $actor->id,
            ...$attributes,
        ]);
    }

    /**
     * Pays the contract's open invoices from its credit balance, oldest
     * first. Returns the amount used. Pass the invoice the caller holds to
     * have that instance updated in place.
     */
    public function applyToOpenInvoices(Contract $contract, ?Invoice $held = null): int
    {
        $available = self::balance($contract->id);

        if ($available <= 0) {
            return 0;
        }

        $invoices = OpenInvoices::lockForContract($contract->id);

        if ($held !== null && isset($invoices[$held->id])) {
            $invoices[$held->id] = $held;
        }
        $plan = PaymentAllocator::allocate($available, OpenInvoices::describe($invoices), OpenInvoices::order($contract));

        foreach ($plan->invoiceIds() as $invoiceId) {
            $entry = $this->record($contract, CreditTransactionType::Applied, -$plan->allocatedTo($invoiceId), ['invoice_id' => $invoiceId]);
            $lines = array_values(array_filter($plan->lines, fn (AllocationLine $line): bool => $line->invoiceId === $invoiceId));

            $this->writer->write($contract, $lines, $invoices, ['credit_transaction_id' => $entry->id]);

            CreditApplied::dispatch($entry);
        }

        return $plan->allocated();
    }

    /**
     * Pays credit back to the resident from a cash or bank account, as at
     * check-out.
     */
    public function refund(Contract $contract, int $amount, string $accountId): CreditTransaction
    {
        $entry = $this->record($contract, CreditTransactionType::Refunded, -$amount);

        CreditRefunded::dispatch($entry, $accountId);

        return $entry;
    }

    /**
     * Takes back credit already spent on invoices, newest first, until the
     * balance covers $needed. Those invoices owe the amount again.
     */
    public function reclaim(Contract $contract, int $needed): void
    {
        while (self::balance($contract->id) < $needed) {
            $allocation = PaymentAllocation::query()
                ->active()
                ->whereIn('credit_transaction_id', CreditTransaction::query()->where('contract_id', $contract->id)->select('id'))
                ->orderByDesc('id')
                ->first() ?? throw new LogicException('Saldo kredit kontrak ini lebih kecil dari yang harus dibalik.');

            $invoice = Invoice::query()->whereKey($allocation->invoice_id)->lockForUpdate()->firstOrFail();

            $this->writer->reverse($contract, $allocation, $invoice);
            $this->record($contract, CreditTransactionType::Reversal, $allocation->amount, ['invoice_id' => $invoice->id]);

            AllocationReleased::dispatch($allocation, $allocation->amount);
        }
    }
}
