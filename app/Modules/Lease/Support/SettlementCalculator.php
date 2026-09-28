<?php

namespace App\Modules\Lease\Support;

use App\Modules\Billing\Support\FinalBilling;
use App\Modules\Billing\Support\RentInvoiceGenerator;
use App\Modules\Billing\Support\UpcomingInvoice;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\Notice;
use App\Modules\Lease\States\Contract\Terminated;
use App\Modules\Payment\Support\CreditLedger;
use App\Modules\Property\Enums\AllocationCategory;
use Carbon\CarbonImmutable;

/**
 * The numbers of a check-out settlement (PRD §8.9):
 * arrears + damage + penalty − deposit − credit.
 */
final class SettlementCalculator
{
    public function __construct(
        private readonly BillingCursor $cursor,
        private readonly RentInvoiceGenerator $generator,
    ) {}

    /**
     * Rent runs to the planned move-out after a notice, the end date of a
     * terminated contract, or the agreed end date.
     */
    public function lastDayOfStay(Contract $contract): ?CarbonImmutable
    {
        return $this->cursor->lastBillableDay($contract);
    }

    /**
     * The penalty proposed for leaving early: the one set when the contract
     * was terminated, or the contract's early-termination penalty when the
     * notice was shorter than the property's notice period.
     */
    public function proposedPenalty(Contract $contract): int
    {
        if ($contract->status->equals(Terminated::class)) {
            return $contract->termination_penalty_amount ?? 0;
        }

        if ($contract->status->equals(Notice::class) && $contract->notice_given_on !== null && $contract->planned_move_out_on !== null) {
            $given = (int) $contract->notice_given_on->diffInDays($contract->planned_move_out_on);
            $required = $contract->property()->firstOrFail()->resolvedSettings()->notice_days;

            return $given < $required ? ($contract->early_termination_penalty_amount ?? 0) : 0;
        }

        return 0;
    }

    /**
     * What the resident owes up to the end of the stay, before damage and
     * penalty: open invoices, less deposit billed but not paid, plus rent not
     * billed yet. An estimate until the settlement is finalized.
     */
    public function estimatedArrears(Contract $contract, CarbonImmutable $today): int
    {
        $lastDay = $this->lastDayOfStay($contract);
        $unbilled = $lastDay === null ? 0 : array_sum(array_map(
            fn (UpcomingInvoice $upcoming): int => $upcoming->rent + $upcoming->utilities,
            $this->generator->upcoming($contract, $lastDay, $today),
        ));

        return FinalBilling::owed($contract) - self::unpaidDeposit($contract) + $unbilled;
    }

    /**
     * @return array{outstanding: int, deposit: int, credit: int}
     */
    public function balances(Contract $contract, CarbonImmutable $today): array
    {
        return [
            'outstanding' => $this->estimatedArrears($contract, $today),
            'deposit' => DepositLedger::balance($contract->id),
            'credit' => CreditLedger::balance($contract->id),
        ];
    }

    public static function result(int $outstanding, int $damage, int $penalty, int $deposit, int $credit): int
    {
        return $outstanding + $damage + $penalty - $deposit - $credit;
    }

    private static function unpaidDeposit(Contract $contract): int
    {
        $unpaid = 0;

        foreach (FinalBilling::openInvoices($contract) as $invoice) {
            $charged = (int) $invoice->items()->where('allocation_category', AllocationCategory::Deposit->value)->sum('amount')
                - (int) $invoice->creditNotes()->where('allocation_category', AllocationCategory::Deposit->value)->sum('amount');
            $paid = (int) $invoice->paymentAllocations()
                ->whereNull('reversed_at')
                ->where('allocation_category', AllocationCategory::Deposit->value)
                ->sum('amount');

            $unpaid += max(0, $charged - $paid);
        }

        return $unpaid;
    }
}
