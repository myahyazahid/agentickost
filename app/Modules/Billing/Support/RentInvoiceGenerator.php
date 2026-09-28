<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Engine\BillingPeriod;
use App\Modules\Billing\Engine\BillingRules;
use App\Modules\Billing\Engine\BillingSchedule;
use App\Modules\Billing\Engine\Proration;
use App\Modules\Billing\Enums\InvoiceItemType;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Billing\States\Invoice\Voided;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Support\BillingCursor;
use Carbon\CarbonImmutable;

/**
 * Rent invoices for a contract (FR-BIL-01, PRD §8.2). Each period has a
 * generation key, so running the job again never bills a period twice
 * (schema §14.4). Call issue() inside the Action's transaction.
 */
final class RentInvoiceGenerator
{
    /**
     * A safety stop for catch-up runs, far above any real backlog.
     */
    private const MAX_PERIODS_PER_RUN = 400;

    public function __construct(
        private readonly InvoiceIssuer $issuer,
        private readonly BillingCursor $cursor,
        private readonly UtilityCharges $utilities,
    ) {}

    /**
     * Periods whose issue date has come, oldest first.
     *
     * @return list<BillingPeriod>
     */
    public function duePeriods(Contract $contract, CarbonImmutable $today): array
    {
        return $this->periodsIssuedBy($contract, $today);
    }

    /**
     * Periods that will be issued up to a date, for the preview.
     *
     * @return list<BillingPeriod>
     */
    public function periodsIssuedBy(Contract $contract, CarbonImmutable $date): array
    {
        $schedule = $this->schedule($contract);
        $start = $this->cursor->nextPeriodStart($contract);
        $periods = [];

        while (count($periods) < self::MAX_PERIODS_PER_RUN) {
            $period = $schedule->periodStartingAt($start);

            if ($period === null || $period->issueDate->greaterThan($date)) {
                break;
            }

            $periods[] = $period;
            $start = $period->end->addDay();
        }

        return $periods;
    }

    /**
     * Periods not billed yet that start on or before a date, whatever their
     * issue date. A room move or check-out bills these right away.
     *
     * @return list<BillingPeriod>
     */
    public function periodsStartingBy(Contract $contract, CarbonImmutable $date): array
    {
        $schedule = $this->schedule($contract);
        $start = $this->cursor->nextPeriodStart($contract);
        $periods = [];

        while (count($periods) < self::MAX_PERIODS_PER_RUN && $start->lessThanOrEqualTo($date)) {
            $period = $schedule->periodStartingAt($start);

            if ($period === null) {
                break;
            }

            $periods[] = $period;
            $start = $period->end->addDay();
        }

        return $periods;
    }

    /**
     * The contract's billing period that starts on a date, as scheduled now.
     */
    public function periodAt(Contract $contract, CarbonImmutable $start): ?BillingPeriod
    {
        return $this->schedule($contract)->periodStartingAt($start);
    }

    /**
     * What will be billed up to a date, without writing anything. The deposit
     * and the readings taken so far go on the first upcoming invoice.
     *
     * @return list<UpcomingInvoice>
     */
    public function upcoming(Contract $contract, CarbonImmutable $until, CarbonImmutable $today): array
    {
        $deposit = $this->depositToBill($contract);
        $upcoming = [];

        foreach ($this->periodsIssuedBy($contract, $until) as $index => $period) {
            $lines = $this->utilities->lines($contract, $period, $today, withReadings: $index === 0);

            $upcoming[] = new UpcomingInvoice(
                $contract,
                $period,
                Proration::amount($this->periodRent($contract, $period), $period->billedDays, $period->basisDays),
                $index === 0 ? $deposit : 0,
                array_sum(array_map(fn (UtilityLine $line): int => $line->amount(), $lines)),
            );
        }

        return $upcoming;
    }

    public function issue(Contract $contract, BillingPeriod $period, CarbonImmutable $issueDate): Invoice
    {
        $key = self::generationKey($contract, $period);
        $existing = Invoice::query()->where('generation_key', $key)->first();

        if ($existing !== null) {
            $this->cursor->moveTo($contract, $period->end->addDay());

            return $existing;
        }

        $deposit = $this->depositToBill($contract);

        $invoice = Invoice::create([
            'property_id' => $contract->property_id,
            'contract_id' => $contract->id,
            'payer_id' => $contract->payer_id,
            'type' => InvoiceType::Rent,
            'generation_key' => $key,
            'period_start' => $period->start,
            'period_end' => $period->end,
            'due_date' => $period->dueDate,
        ]);

        $sortOrder = 0;
        $rentBasis = $this->periodRent($contract, $period);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'type' => InvoiceItemType::Rent,
            'allocation_category' => InvoiceItemType::Rent->allocationCategory(),
            'description' => $this->rentDescription($contract, $period),
            'quantity' => 1,
            'unit_amount' => $rentBasis,
            'amount' => Proration::amount($rentBasis, $period->billedDays, $period->basisDays),
            'period_start' => $period->start,
            'period_end' => $period->end,
            'sort_order' => $sortOrder++,
        ]);

        if ($deposit > 0) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'type' => InvoiceItemType::Deposit,
                'allocation_category' => InvoiceItemType::Deposit->allocationCategory(),
                'description' => 'Deposit',
                'quantity' => 1,
                'unit_amount' => $deposit,
                'amount' => $deposit,
                'sort_order' => $sortOrder++,
            ]);
        }

        $this->utilities->addTo($invoice, $contract, $period, $issueDate, $sortOrder);

        $this->issuer->issue($invoice, $issueDate);
        $this->cursor->moveTo($contract, $period->end->addDay());

        return $invoice;
    }

    /**
     * The contract rent, or the hold rate when a hold covers the period's
     * first day (FR-KTR-05).
     */
    public function periodRent(Contract $contract, BillingPeriod $period): int
    {
        $hold = $contract->holds()
            ->whereDate('start_date', '<=', $period->start)
            ->whereDate('end_date', '>=', $period->start)
            ->first();

        return $hold->rent_amount ?? $contract->rent_amount;
    }

    /**
     * The first invoice carries the deposit (docs/adr/0008). A renewal only
     * bills what its deposit adds to the one carried over.
     */
    public function depositToBill(Contract $contract): int
    {
        $billed = Invoice::query()
            ->where('contract_id', $contract->id)
            ->where('type', InvoiceType::Rent->value)
            ->whereNot('status', Voided::$name)
            ->exists();

        if ($billed) {
            return 0;
        }

        $previous = $contract->renewedFrom()->first();

        return max(0, $contract->deposit_amount - ($previous->deposit_amount ?? 0));
    }

    public static function generationKey(Contract $contract, BillingPeriod $period): string
    {
        return "rent:{$contract->id}:{$period->start->toDateString()}";
    }

    private function schedule(Contract $contract): BillingSchedule
    {
        $settings = $contract->property()->firstOrFail()->resolvedSettings();

        return new BillingSchedule(
            new BillingRules(
                $contract->rental_period,
                $settings->billing_mode,
                $contract->billing_anchor_day,
                $settings->proration_basis,
                $settings->invoice_lead_days,
            ),
            $this->cursor->lastBillableDay($contract),
        );
    }

    private function rentDescription(Contract $contract, BillingPeriod $period): string
    {
        $room = $contract->room()->first();
        $description = sprintf(
            'Sewa kamar %s, %s sampai %s',
            $room?->number,
            $period->start->translatedFormat('j M Y'),
            $period->end->translatedFormat('j M Y'),
        );

        return $period->isPartial()
            ? "{$description} (prorata {$period->billedDays}/{$period->basisDays} hari)"
            : $description;
    }
}
