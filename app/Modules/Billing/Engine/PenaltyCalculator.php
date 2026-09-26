<?php

namespace App\Modules\Billing\Engine;

use App\Modules\Property\Enums\PenaltyType;
use Carbon\CarbonImmutable;

/**
 * Late penalties owed on an invoice up to today (PRD §8.4).
 *
 * The first penalty day is the day after the grace period. Flat and percent
 * penalties are charged once, dated that day; a daily penalty is charged for
 * every late day up to today. Days that already have an accrual, waived or
 * not, are skipped, so the job can run again and catch up missed days.
 * Waived accruals do not count towards the cap.
 */
final class PenaltyCalculator
{
    public function __construct(private readonly PenaltyRules $rules) {}

    /**
     * @param  list<CarbonImmutable>  $existingDates  days that already have an accrual
     * @param  int  $activeTotal  accrued and not waived
     * @return list<PenaltyAccrualDraft>
     */
    public function accrue(
        CarbonImmutable $dueDate,
        CarbonImmutable $today,
        int $principalOutstanding,
        array $existingDates,
        int $activeTotal,
    ): array {
        if ($this->rules->type === PenaltyType::None || $principalOutstanding <= 0) {
            return [];
        }

        $firstDay = $dueDate->addDays($this->rules->graceDays + 1);

        if ($today->lessThan($firstDay)) {
            return [];
        }

        $accrued = array_map(fn (CarbonImmutable $date): string => $date->toDateString(), $existingDates);
        $room = $this->rules->maxAmount === null ? PHP_INT_MAX : max(0, $this->rules->maxAmount - $activeTotal);
        $drafts = [];

        foreach ($this->days($firstDay, $today) as $day) {
            if (in_array($day->toDateString(), $accrued, true)) {
                continue;
            }

            $amount = min($this->amountFor($principalOutstanding), $room);

            if ($amount <= 0) {
                break;
            }

            $drafts[] = new PenaltyAccrualDraft($day, $amount);
            $room -= $amount;
        }

        return $drafts;
    }

    /**
     * Candidate days: only the first penalty day for one-off penalties,
     * every day up to today for daily ones, unless one was already charged.
     *
     * @return list<CarbonImmutable>
     */
    private function days(CarbonImmutable $firstDay, CarbonImmutable $today): array
    {
        if ($this->rules->type !== PenaltyType::Daily) {
            return [$firstDay];
        }

        $days = [];

        for ($day = $firstDay; $day->lessThanOrEqualTo($today); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }

    private function amountFor(int $principalOutstanding): int
    {
        return match ($this->rules->type) {
            PenaltyType::Flat, PenaltyType::Daily => (int) $this->rules->amount,
            PenaltyType::Percent => Proration::divideRounded($principalOutstanding * self::basisPoints($this->rules->percent), 10_000),
            PenaltyType::None => 0,
        };
    }

    /**
     * "2.50" becomes 250 basis points, without going through a float.
     */
    private static function basisPoints(?string $percent): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $percent, 2), 2, '0');

        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
