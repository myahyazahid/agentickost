<?php

namespace App\Modules\Payment\Listeners;

use App\Modules\Billing\Events\CreditNoteIssued;
use App\Modules\Billing\Models\CreditNote;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Support\InvoiceBalance;
use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Engine\AllocationLine;
use App\Modules\Payment\Enums\CreditTransactionType;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Support\AllocationWriter;
use App\Modules\Payment\Support\CreditLedger;
use App\Modules\Payment\Support\OpenInvoices;
use App\Modules\Property\Enums\AllocationCategory;

/**
 * A credit note on an invoice that was already paid leaves money paid for
 * nothing (PRD §8.10). That part of the allocations is released and goes
 * back where it came from: a payment's money becomes credit balance, credit
 * returns to the credit balance, deposit returns to the deposit.
 *
 * The credited component is released first, then the others from the end of
 * the allocation order, so penalty goes before rent and deposit goes last.
 */
final class ReleaseOverpaidCredit
{
    public function __construct(
        private readonly AllocationWriter $writer,
        private readonly CreditLedger $credit,
        private readonly DepositLedger $deposits,
    ) {}

    public function handle(CreditNoteIssued $event): void
    {
        $note = $event->creditNote;
        $invoice = Invoice::query()->whereKey($note->invoice_id)->lockForUpdate()->firstOrFail();
        $excess = -InvoiceBalance::outstanding($invoice);

        if ($excess <= 0 || $invoice->contract_id === null) {
            return;
        }

        $contract = Contract::query()->whereKey($invoice->contract_id)->firstOrFail();

        foreach ($this->releaseOrder($invoice, $contract, $note) as $allocation) {
            if ($excess <= 0) {
                break;
            }

            $released = min($excess, $allocation->amount);

            $this->writer->reverse($contract, $allocation, $invoice);

            if ($allocation->amount > $released) {
                $this->writer->write(
                    $contract,
                    [new AllocationLine($invoice->id, $allocation->allocation_category, $allocation->amount - $released)],
                    [$invoice->id => $invoice],
                    AllocationWriter::sourceOf($allocation),
                );
            }

            $this->giveBack($contract, $allocation, $released, $note);
            $excess -= $released;
        }
    }

    /**
     * @return list<PaymentAllocation>
     */
    private function releaseOrder(Invoice $invoice, Contract $contract, CreditNote $note): array
    {
        $rank = array_flip(array_map(
            fn (AllocationCategory $category): string => $category->value,
            array_reverse(OpenInvoices::order($contract)),
        ));
        $rank[$note->allocation_category->value] = -1;

        $allocations = array_values(PaymentAllocation::query()
            ->active()
            ->where('invoice_id', $invoice->id)
            ->orderByDesc('id')
            ->get()
            ->all());

        usort($allocations, fn (PaymentAllocation $a, PaymentAllocation $b): int => $rank[$a->allocation_category->value] <=> $rank[$b->allocation_category->value]);

        return $allocations;
    }

    private function giveBack(Contract $contract, PaymentAllocation $allocation, int $amount, CreditNote $note): void
    {
        if ($allocation->payment_id !== null) {
            $this->credit->record($contract, CreditTransactionType::Overpayment, $amount, [
                'payment_id' => $allocation->payment_id,
                'invoice_id' => $allocation->invoice_id,
            ]);
        } elseif ($allocation->credit_transaction_id !== null) {
            $this->credit->record($contract, CreditTransactionType::Reversal, $amount, ['invoice_id' => $allocation->invoice_id]);
        } else {
            $this->deposits->record($contract, DepositTransactionType::Reversal, $amount, [
                'invoice_id' => $allocation->invoice_id,
                'reason' => "Dikembalikan karena nota kredit {$note->number}",
            ]);
        }
    }
}
