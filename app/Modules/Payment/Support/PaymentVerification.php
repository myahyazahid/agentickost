<?php

namespace App\Modules\Payment\Support;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Support\DocumentNumbers;
use App\Modules\Finance\Support\StaffCashAccounts;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Engine\PaymentAllocator;
use App\Modules\Payment\Enums\CreditTransactionType;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Events\PaymentVerified;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\States\Payment\Verified;
use App\Support\Actors\ActorContext;
use App\Support\Money\Rupiah;
use App\Support\States\StateTransition;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Verifies a pending payment: numbers its receipt (FR-PAY-06), allocates it
 * to the contract's open invoices, automatically or to the invoices chosen
 * (FR-PAY-04), and puts the rest on the credit balance (FR-PAY-05). Call
 * inside a transaction that locked the contract and the payment.
 */
final class PaymentVerification
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly ActorContext $actors,
        private readonly AllocationWriter $writer,
        private readonly CreditLedger $credit,
    ) {}

    /**
     * @param  array<string, int>|null  $targets  Amount per invoice id, or null to allocate automatically
     */
    public function verify(Payment $payment, Contract $contract, ?array $targets = null): void
    {
        $property = $payment->property()->firstOrFail();
        $actor = $this->actors->current();

        $payment->receipt_number = $this->numbers->next(DocumentType::Receipt, $property->today(), $property->code);
        $payment->verified_by_type = $actor->type;
        $payment->verified_by_id = $actor->id;
        $payment->verified_at = now();
        StateTransition::to($payment->status, Verified::class, 'amount');

        $invoices = OpenInvoices::lockForContract($contract->id);
        $order = OpenInvoices::order($contract);

        if ($targets === null || $targets === []) {
            $plan = PaymentAllocator::allocate($payment->amount, OpenInvoices::describe($invoices), $order);
        } else {
            self::checkTargets($payment, $invoices, $targets);

            try {
                $plan = PaymentAllocator::allocateTo($payment->amount, OpenInvoices::describe($invoices), $targets, $order);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['allocations' => 'Alokasi melebihi sisa salah satu tagihan.']);
            }
        }

        $this->writer->write($contract, $plan->lines, $invoices, ['payment_id' => $payment->id]);

        if ($plan->remainder > 0) {
            $this->credit->record($contract, CreditTransactionType::Overpayment, $plan->remainder, ['payment_id' => $payment->id]);
        }

        if ($payment->method === PaymentMethod::Cash) {
            $receiver = $payment->receivedBy()->firstOrFail();

            if (StaffCash::tracks($receiver)) {
                StaffCashAccounts::for($receiver);
            }
        }

        PaymentVerified::dispatch($payment);
    }

    /**
     * @param  array<string, Invoice>  $invoices
     * @param  array<string, int>  $targets
     */
    private static function checkTargets(Payment $payment, array $invoices, array $targets): void
    {
        foreach ($targets as $invoiceId => $amount) {
            $invoice = $invoices[$invoiceId] ?? null;

            if ($invoice === null) {
                throw ValidationException::withMessages(['allocations' => 'Tagihan yang dipilih tidak sedang menunggu pembayaran kontrak ini.']);
            }

            if ($amount > $invoice->balance_amount) {
                throw ValidationException::withMessages([
                    'allocations' => "Sisa tagihan {$invoice->number} hanya ".Rupiah::format($invoice->balance_amount).'.',
                ]);
            }
        }

        if (array_sum($targets) > $payment->amount) {
            throw ValidationException::withMessages([
                'allocations' => 'Jumlah yang dialokasikan melebihi pembayaran '.Rupiah::format($payment->amount).'.',
            ]);
        }
    }
}
