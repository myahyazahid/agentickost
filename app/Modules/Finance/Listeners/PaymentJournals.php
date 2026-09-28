<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Journal\JournalLineDraft;
use App\Modules\Finance\Journal\JournalPoster;
use App\Modules\Finance\Journal\LedgerAccounts;
use App\Modules\Finance\Support\StaffCashAccounts;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Events\AllocationReleased;
use App\Modules\Payment\Events\CashHandoverConfirmed;
use App\Modules\Payment\Events\CreditApplied;
use App\Modules\Payment\Events\CreditRefunded;
use App\Modules\Payment\Events\PaymentReversed;
use App\Modules\Payment\Events\PaymentVerified;
use App\Modules\Payment\Models\CreditTransaction;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Support\StaffCash;
use App\Support\Money\Rupiah;
use Carbon\CarbonImmutable;
use Illuminate\Events\Dispatcher;
use LogicException;

/**
 * Journals for money received (PRD §8.14). Allocated money settles the
 * receivable, or the deposit owed for the deposit component; money beyond
 * the open invoices becomes credit owed to the resident.
 */
final class PaymentJournals
{
    public function __construct(private readonly JournalPoster $journals) {}

    public function paymentVerified(PaymentVerified $event): void
    {
        $payment = $event->payment;
        $lines = [JournalLineDraft::debit(self::moneyAccount($payment), $payment->amount)];

        foreach ($payment->allocations()->active()->get() as $allocation) {
            $lines[] = self::settles($allocation, -$allocation->amount);
        }

        $credit = (int) CreditTransaction::query()->where('payment_id', $payment->id)->sum('amount');

        if ($credit !== 0) {
            $lines[] = JournalLineDraft::credit(LedgerAccounts::id(AccountSubtype::CreditLiability), $credit, $payment->contract_id);
        }

        $property = $payment->property()->firstOrFail();

        $this->journals->post(
            JournalEvent::PaymentVerified,
            CarbonImmutable::parse($payment->paid_at->timezone($property->timezone->value)->toDateString()),
            "Pembayaran {$payment->receipt_number}",
            $payment,
            $property,
            $lines,
        );
    }

    public function paymentReversed(PaymentReversed $event): void
    {
        $payment = $event->payment;
        $lines = [JournalLineDraft::credit(self::moneyAccount($payment), $payment->amount)];

        foreach ($event->allocations as $allocation) {
            $lines[] = self::settles($allocation, $allocation->amount);
        }

        if ($event->creditTakenBack > 0) {
            $lines[] = JournalLineDraft::debit(LedgerAccounts::id(AccountSubtype::CreditLiability), $event->creditTakenBack, $payment->contract_id);
        }

        $property = $payment->property()->firstOrFail();

        $this->journals->post(
            JournalEvent::PaymentReversed,
            $property->today(),
            "Pembayaran {$payment->receipt_number} dibalik",
            $payment,
            $property,
            $lines,
        );
    }

    public function creditApplied(CreditApplied $event): void
    {
        $entry = $event->entry;
        $lines = [JournalLineDraft::debit(LedgerAccounts::id(AccountSubtype::CreditLiability), -$entry->amount, $entry->contract_id)];

        foreach (PaymentAllocation::query()->where('credit_transaction_id', $entry->id)->get() as $allocation) {
            $lines[] = self::settles($allocation, -$allocation->amount);
        }

        $contract = $entry->contract()->firstOrFail();

        $this->journals->post(
            JournalEvent::CreditApplied,
            $entry->occurred_on,
            'Saldo kredit dipakai untuk tagihan '.$entry->invoice()->value('number'),
            $entry,
            $contract->property()->firstOrFail(),
            $lines,
        );
    }

    public function creditRefunded(CreditRefunded $event): void
    {
        $entry = $event->entry;
        $contract = $entry->contract()->firstOrFail();

        $this->journals->post(
            JournalEvent::CreditRefunded,
            $entry->occurred_on,
            "Saldo kredit kontrak {$contract->number} dikembalikan",
            $entry,
            $contract->property()->firstOrFail(),
            [
                JournalLineDraft::debit(LedgerAccounts::id(AccountSubtype::CreditLiability), -$entry->amount, $contract->id),
                JournalLineDraft::credit($event->accountId, -$entry->amount),
            ],
        );
    }

    /**
     * The invoice owes the amount again; the money goes back to the credit
     * balance, or to the deposit it came from.
     */
    public function allocationReleased(AllocationReleased $event): void
    {
        $allocation = $event->allocation;
        $invoice = $allocation->invoice()->firstOrFail();
        $returnedTo = $allocation->deposit_transaction_id !== null ? AccountSubtype::DepositLiability : AccountSubtype::CreditLiability;

        $this->journals->post(
            JournalEvent::AllocationReleased,
            $invoice->property()->firstOrFail()->today(),
            "Pembayaran tagihan {$invoice->number} dilepas ".Rupiah::format($event->amount),
            $allocation,
            $invoice->property()->firstOrFail(),
            [
                self::settles($allocation, $event->amount),
                JournalLineDraft::credit(LedgerAccounts::id($returnedTo), $event->amount, $invoice->contract_id),
            ],
        );
    }

    /**
     * Cash leaves the staff member's account at the amount they were
     * holding; a shortfall is an expense and a surplus is income, both
     * explained on the handover.
     */
    public function cashHandedOver(CashHandoverConfirmed $event): void
    {
        $handover = $event->handover;
        $staff = $handover->staff()->firstOrFail();
        $difference = $handover->actual_amount - $handover->expected_amount;
        $lines = [
            JournalLineDraft::debit($handover->destination_account_id, $handover->actual_amount),
            JournalLineDraft::credit(StaffCashAccounts::for($staff)->id, $handover->expected_amount),
        ];

        if ($difference < 0) {
            $lines[] = JournalLineDraft::debit(LedgerAccounts::id(AccountSubtype::OtherExpense), -$difference);
        } elseif ($difference > 0) {
            $lines[] = JournalLineDraft::credit(LedgerAccounts::id(AccountSubtype::OtherRevenue), $difference);
        }

        $property = $handover->property()->firstOrFail();

        $this->journals->post(
            JournalEvent::CashHandedOver,
            $property->today(),
            "Setoran kas {$staff->name}",
            $handover,
            $property,
            $lines,
        );
    }

    /**
     * Where the money of a payment is: the bank account it was sent to, the
     * cash in hand of the staff member who took it, or the tenant's cash
     * account for cash the owner took (PRD §8.12).
     */
    public static function moneyAccount(Payment $payment): string
    {
        return match ($payment->method) {
            PaymentMethod::Transfer => $payment->bankAccount()->firstOrFail()->ledger_account_id,
            PaymentMethod::Cash => self::cashAccount($payment->receivedBy()->firstOrFail()),
            PaymentMethod::Gateway => throw new LogicException('Pembayaran gateway belum didukung (M1.5.2).'),
        };
    }

    private static function cashAccount(User $receiver): string
    {
        return StaffCash::tracks($receiver) ? StaffCashAccounts::for($receiver)->id : LedgerAccounts::id(AccountSubtype::Cash);
    }

    /**
     * A signed line on the account an allocation settles, for its contract.
     */
    private static function settles(PaymentAllocation $allocation, int $amount): JournalLineDraft
    {
        $contractId = $allocation->invoice()->value('contract_id');

        return new JournalLineDraft(
            LedgerAccounts::settledBy($allocation->allocation_category),
            $amount,
            is_string($contractId) ? $contractId : null,
        );
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            PaymentVerified::class => 'paymentVerified',
            PaymentReversed::class => 'paymentReversed',
            CreditApplied::class => 'creditApplied',
            CreditRefunded::class => 'creditRefunded',
            AllocationReleased::class => 'allocationReleased',
            CashHandoverConfirmed::class => 'cashHandedOver',
        ];
    }
}
