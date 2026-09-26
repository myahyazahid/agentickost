<?php

/*
 * PRD §8.1 (money) and §8.3 (proration), written before the implementation.
 */

use App\Modules\Billing\Engine\BillingRules;
use App\Modules\Billing\Engine\BillingSchedule;
use App\Modules\Billing\Engine\Proration;
use App\Modules\Billing\Engine\TotalRounding;
use App\Modules\Property\Enums\BillingMode;
use App\Modules\Property\Enums\ProrationBasis;
use App\Modules\Property\Enums\RentalPeriod;
use Carbon\CarbonImmutable;

function firstPeriodRent(
    int $rent,
    string $moveIn,
    ProrationBasis $basis = ProrationBasis::ActualDays,
    RentalPeriod $period = RentalPeriod::Monthly,
    int $fixedDay = 1,
): int {
    $schedule = new BillingSchedule(new BillingRules($period, BillingMode::FixedDate, $fixedDay, $basis, 7));
    $first = $schedule->periodStartingAt(CarbonImmutable::parse($moveIn)) ?? throw new LogicException;

    return Proration::amount($rent, $first->billedDays, $first->basisDays);
}

it('bills Rp440.000 for 20 to 30 September at Rp1.200.000 a month (PRD 8.3 example)', function (ProrationBasis $basis) {
    expect(firstPeriodRent(1_200_000, '2026-09-20', $basis))->toBe(440_000);
})->with([ProrationBasis::ActualDays, ProrationBasis::ThirtyDays]);

it('prorates by the actual days of the month or by 30 days', function (string $moveIn, ProrationBasis $basis, int $expected) {
    expect(firstPeriodRent(1_200_000, $moveIn, $basis))->toBe($expected);
})->with([
    '12 of 31 October days' => ['2026-10-20', ProrationBasis::ActualDays, 464_516],
    '12 days on a 30-day basis' => ['2026-10-20', ProrationBasis::ThirtyDays, 480_000],
    '14 of 28 February days' => ['2026-02-15', ProrationBasis::ActualDays, 600_000],
    '14 February days on a 30-day basis' => ['2026-02-15', ProrationBasis::ThirtyDays, 560_000],
]);

it('never charges more than a full period on a 30-day basis', function () {
    expect(firstPeriodRent(1_200_000, '2026-01-02', ProrationBasis::ThirtyDays))->toBe(1_200_000);
});

it('prorates a short first quarter against the quarter it belongs to', function () {
    // 20 to 30 September out of 1 July to 30 September (92 days).
    expect(firstPeriodRent(3_300_000, '2026-09-20', period: RentalPeriod::Quarterly))->toBe(394_565);
});

it('prorates the last period when the contract ends early', function () {
    $schedule = new BillingSchedule(
        new BillingRules(RentalPeriod::Weekly, BillingMode::Anniversary, 16, ProrationBasis::ActualDays, 7),
        CarbonImmutable::parse('2026-09-25'),
    );
    $last = $schedule->periodStartingAt(CarbonImmutable::parse('2026-09-23')) ?? throw new LogicException;

    expect(Proration::amount(700_000, $last->billedDays, $last->basisDays))->toBe(300_000);
});

it('rounds each prorated amount to the nearest rupiah, halves up', function (int $rent, int $days, int $basis, int $expected) {
    expect(Proration::amount($rent, $days, $basis))->toBe($expected);
})->with([
    'a third, rounded down' => [1_000_000, 1, 3, 333_333],
    'two thirds, rounded up' => [1_000_000, 2, 3, 666_667],
    'exactly half a rupiah' => [1, 1, 2, 1],
    'a full period' => [1_200_000, 30, 30, 1_200_000],
]);

it('rounds the invoice total to the property unit and returns the difference', function (int $total, int $unit, int $difference) {
    expect(TotalRounding::difference($total, $unit))->toBe($difference);
})->with([
    'no rounding' => [1_234_567, 1, 0],
    'up to the next thousand' => [1_234_567, 1_000, 433],
    'down to the thousand' => [1_234_400, 1_000, -400],
    'half rounds up' => [1_234_500, 1_000, 500],
    'to the nearest hundred' => [1_234_560, 100, 40],
]);
