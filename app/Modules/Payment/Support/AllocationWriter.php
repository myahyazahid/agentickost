<?php

namespace App\Modules\Payment\Support;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Support\InvoicePayments;
use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Engine\AllocationLine;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Property\Enums\AllocationCategory;
use Illuminate\Validation\ValidationException;

/**
 * Writes allocations and keeps the invoice totals and the deposit ledger in
 * step with them: money allocated to the deposit component is deposit
 * received (FR-DEP-01). Call inside a transaction holding the contract and
 * invoice locks.
 */
final class AllocationWriter
{
    public function __construct(private readonly DepositLedger $deposits) {}

    /**
     * @param  list<AllocationLine>  $lines
     * @param  array<string, Invoice>  $invoices  The invoices named in the lines, locked
     * @param  array{payment_id?: string, credit_transaction_id?: string, deposit_transaction_id?: string}  $source
     * @return list<PaymentAllocation>
     */
    public function write(Contract $contract, array $lines, array $invoices, array $source): array
    {
        $created = [];
        $perInvoice = [];

        foreach ($lines as $line) {
            $allocation = PaymentAllocation::create([
                ...$source,
                'invoice_id' => $line->invoiceId,
                'allocation_category' => $line->category,
                'amount' => $line->amount,
            ]);

            if ($line->category === AllocationCategory::Deposit) {
                $this->deposits->record($contract, DepositTransactionType::Received, $line->amount, [
                    'payment_allocation_id' => $allocation->id,
                ]);
            }

            $perInvoice[$line->invoiceId] = ($perInvoice[$line->invoiceId] ?? 0) + $line->amount;
            $created[] = $allocation;
        }

        foreach ($perInvoice as $invoiceId => $amount) {
            InvoicePayments::apply($invoices[$invoiceId], $amount);
        }

        return $created;
    }

    /**
     * Undo one allocation: the invoice owes that part again, and a deposit
     * received through it is taken back out of the ledger.
     *
     * @throws ValidationException when that deposit was already used
     */
    public function reverse(Contract $contract, PaymentAllocation $allocation, Invoice $invoice): void
    {
        if ($allocation->allocation_category === AllocationCategory::Deposit
            && DepositLedger::balance($contract->id) < $allocation->amount) {
            throw ValidationException::withMessages([
                'reason' => 'Deposit dari pembayaran ini sudah dipotong, dikembalikan, atau dipindahkan, jadi belum bisa dibalik.',
            ]);
        }

        $allocation->reversed_at = now();
        $allocation->save();

        InvoicePayments::release($invoice, $allocation->amount);

        if ($allocation->allocation_category === AllocationCategory::Deposit) {
            $this->deposits->record($contract, DepositTransactionType::Reversal, -$allocation->amount, [
                'payment_allocation_id' => $allocation->id,
            ]);
        }
    }

    /**
     * The source columns of an allocation, to write a replacement from the
     * same money.
     *
     * @return array{payment_id?: string, credit_transaction_id?: string, deposit_transaction_id?: string}
     */
    public static function sourceOf(PaymentAllocation $allocation): array
    {
        return match (true) {
            $allocation->payment_id !== null => ['payment_id' => $allocation->payment_id],
            $allocation->credit_transaction_id !== null => ['credit_transaction_id' => $allocation->credit_transaction_id],
            default => ['deposit_transaction_id' => (string) $allocation->deposit_transaction_id],
        };
    }
}
