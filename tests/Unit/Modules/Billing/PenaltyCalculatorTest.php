<?php

/*
 * PRD §8.4 late penalties, written before the implementation.
 */

use App\Modules\Billing\Engine\PenaltyCalculator;
use App\Modules\Billing\Engine\PenaltyRules;
use App\Modules\Property\Enums\PenaltyType;
use Carbon\CarbonImmutable;

/**
 * @param  list<string>  $existingDates  dates already accrued, waived or not
 * @return array<string, int> date => amount
 */
function accrue(
    PenaltyType $type,
    string $today,
    ?int $amount = null,
    ?string $percent = null,
    ?int $max = null,
    int $graceDays = 3,
    int $principal = 1_200_000,
    array $existingDates = [],
    int $activeTotal = 0,
): array {
    $calculator = new PenaltyCalculator(new PenaltyRules($type, $graceDays, $amount, $percent, $max));

    $accruals = $calculator->accrue(
        dueDate: CarbonImmutable::parse('2026-09-01'),
        today: CarbonImmutable::parse($today),
        principalOutstanding: $principal,
        existingDates: array_map(CarbonImmutable::parse(...), $existingDates),
        activeTotal: $activeTotal,
    );

    $result = [];

    foreach ($accruals as $accrual) {
        $result[$accrual->date->toDateString()] = $accrual->amount;
    }

    return $result;
}

it('charges nothing during the grace period', function () {
    expect(accrue(PenaltyType::Flat, '2026-09-04', amount: 50_000))->toBe([]);
});

it('charges a flat penalty once, dated the first day after the grace period', function () {
    expect(accrue(PenaltyType::Flat, '2026-09-10', amount: 50_000))->toBe(['2026-09-05' => 50_000])
        ->and(accrue(PenaltyType::Flat, '2026-09-11', amount: 50_000, existingDates: ['2026-09-05'], activeTotal: 50_000))->toBe([]);
});

it('charges a daily penalty for every late day up to today, catching up missed days', function () {
    expect(accrue(PenaltyType::Daily, '2026-09-07', amount: 10_000))->toBe([
        '2026-09-05' => 10_000,
        '2026-09-06' => 10_000,
        '2026-09-07' => 10_000,
    ]);
});

it('skips days already accrued, so the job can run again safely', function () {
    expect(accrue(PenaltyType::Daily, '2026-09-07', amount: 10_000, existingDates: ['2026-09-05', '2026-09-06'], activeTotal: 20_000))
        ->toBe(['2026-09-07' => 10_000]);
});

it('stops at the cap per invoice, charging only what is left on the last day', function () {
    expect(accrue(PenaltyType::Daily, '2026-09-12', amount: 15_000, max: 50_000))->toBe([
        '2026-09-05' => 15_000,
        '2026-09-06' => 15_000,
        '2026-09-07' => 15_000,
        '2026-09-08' => 5_000,
    ]);
});

it('counts only penalties that were not waived towards the cap', function () {
    expect(accrue(PenaltyType::Daily, '2026-09-08', amount: 10_000, max: 30_000, existingDates: ['2026-09-05', '2026-09-06', '2026-09-07'], activeTotal: 10_000))
        ->toBe(['2026-09-08' => 10_000]);
});

it('does not charge a waived flat penalty again', function () {
    expect(accrue(PenaltyType::Flat, '2026-09-20', amount: 50_000, existingDates: ['2026-09-05'], activeTotal: 0))->toBe([]);
});

it('charges a percentage of the unpaid balance once', function () {
    expect(accrue(PenaltyType::Percent, '2026-09-10', percent: '2.50', principal: 1_000_001))->toBe(['2026-09-05' => 25_000]);
});

it('charges nothing without a penalty rule or an unpaid balance', function (PenaltyType $type, int $principal) {
    expect(accrue($type, '2026-09-10', amount: 50_000, principal: $principal))->toBe([]);
})->with([
    'no penalty rule' => [PenaltyType::None, 1_200_000],
    'nothing left to pay' => [PenaltyType::Daily, 0],
]);

it('starts on the day after the due date without a grace period', function () {
    expect(accrue(PenaltyType::Flat, '2026-09-02', amount: 50_000, graceDays: 0))->toBe(['2026-09-02' => 50_000]);
});
