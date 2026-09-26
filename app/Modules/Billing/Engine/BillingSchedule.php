<?php

namespace App\Modules\Billing\Engine;

use App\Modules\Property\Enums\ProrationBasis;
use Carbon\CarbonImmutable;

/**
 * Works out a contract's billing periods (PRD §8.2).
 *
 * - Anniversary: each period starts on the anchor day; in shorter months the
 *   last day of the month stands in for the 29th to 31st.
 * - Fixed date: the first period runs to the day before the next fixed date
 *   and is prorated; after that, periods start on the fixed date.
 * - A period that would run past the contract end is cut there and prorated.
 * - Rent is due on the first day of the period and issued the lead days
 *   before, but never before the previous period of the same length began.
 */
final class BillingSchedule
{
    public function __construct(
        private readonly BillingRules $rules,
        private readonly ?CarbonImmutable $contractEnd = null,
    ) {}

    public function periodStartingAt(CarbonImmutable $start): ?BillingPeriod
    {
        $start = $start->startOfDay();

        if ($this->contractEnd !== null && $start->greaterThan($this->contractEnd)) {
            return null;
        }

        [$end, $referenceStart, $referenceEnd] = $this->naturalPeriod($start);

        $fullLength = self::days($referenceStart, $referenceEnd);

        if ($this->contractEnd !== null && $end->greaterThan($this->contractEnd)) {
            $end = $this->contractEnd;
        }

        $billedDays = self::days($start, $end);
        $basisDays = $this->basisDays($fullLength);

        return new BillingPeriod(
            start: $start,
            end: $end,
            dueDate: $start,
            issueDate: $start->subDays(min($this->rules->leadDays, $fullLength - 1)),
            billedDays: min($billedDays, $basisDays),
            basisDays: $basisDays,
        );
    }

    /**
     * The period's own end, and the full period it is measured against.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function naturalPeriod(CarbonImmutable $start): array
    {
        $days = $this->rules->period->days();

        if ($days !== null) {
            $end = $start->addDays($days - 1);

            return [$end, $start, $end];
        }

        $months = (int) $this->rules->period->months();
        $anchor = $this->rules->anchorDay;

        if ($this->rules->usesFixedDate() && $start->day !== $anchor) {
            $nextBoundary = self::anchoredDate($start->year, $start->month, $anchor);

            if ($nextBoundary->lessThanOrEqualTo($start)) {
                $nextBoundary = self::anchoredDate($start->year, $start->month + 1, $anchor);
            }

            $referenceStart = self::anchoredDate($nextBoundary->year, $nextBoundary->month - $months, $anchor);

            return [$nextBoundary->subDay(), $referenceStart, $nextBoundary->subDay()];
        }

        $end = self::anchoredDate($start->year, $start->month + $months, $anchor)->subDay();

        return [$end, $start, $end];
    }

    private function basisDays(int $fullLength): int
    {
        if ($this->rules->period->isMonthBased() && $this->rules->basis === ProrationBasis::ThirtyDays) {
            return 30 * (int) $this->rules->period->months();
        }

        return $fullLength;
    }

    /**
     * The anchor day in a month, or the month's last day when it is shorter.
     * Month numbers outside 1 to 12 roll over into other years.
     */
    private static function anchoredDate(int $year, int $month, int $anchor): CarbonImmutable
    {
        $firstOfMonth = CarbonImmutable::create($year, 1, 1)?->addMonths($month - 1)
            ?? throw new \LogicException('Tanggal tidak valid.');

        return $firstOfMonth->day(min($anchor, $firstOfMonth->daysInMonth));
    }

    private static function days(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $from->diffInDays($to) + 1;
    }
}
