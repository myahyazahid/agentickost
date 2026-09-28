<?php

namespace App\Modules\Payment\Support;

use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Finance\Events\DepositDeducted;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Engine\PaymentAllocator;
use App\Modules\Property\Enums\AllocationCategory;

/**
 * Pays a contract's open invoices from its deposit, oldest first, as at
 * check-out (PRD §8.6, §8.9). The deposit never pays its own deposit
 * component. Call inside a transaction that locked the contract.
 */
final class DepositPayments
{
    public function __construct(
        private readonly DepositLedger $deposits,
        private readonly AllocationWriter $writer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  Extra deposit ledger columns, such as settlement_id
     * @return int The deposit used
     */
    public function payOpenInvoices(Contract $contract, string $reason, array $attributes = []): int
    {
        $available = DepositLedger::balance($contract->id);

        if ($available <= 0) {
            return 0;
        }

        $invoices = OpenInvoices::lockForContract($contract->id);
        $plan = PaymentAllocator::allocate($available, OpenInvoices::describe($invoices), OpenInvoices::order($contract), except: [AllocationCategory::Deposit]);

        foreach ($plan->invoiceIds() as $invoiceId) {
            $entry = $this->deposits->record($contract, DepositTransactionType::Deducted, -$plan->allocatedTo($invoiceId), [
                'invoice_id' => $invoiceId,
                'reason' => $reason,
                ...$attributes,
            ]);

            $lines = array_values(array_filter($plan->lines, fn ($line): bool => $line->invoiceId === $invoiceId));
            $this->writer->write($contract, $lines, $invoices, ['deposit_transaction_id' => $entry->id]);

            DepositDeducted::dispatch($entry);
        }

        return $plan->allocated();
    }
}
