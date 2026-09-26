<?php

/*
 * PRD §8.2 billing cycles, written before the implementation (roadmap M1.3).
 */

use App\Modules\Billing\Engine\BillingPeriod;
use App\Modules\Billing\Engine\BillingRules;
use App\Modules\Billing\Engine\BillingSchedule;
use App\Modules\Property\Enums\BillingMode;
use App\Modules\Property\Enums\ProrationBasis;
use App\Modules\Property\Enums\RentalPeriod;
use Carbon\CarbonImmutable;

function schedule(
    RentalPeriod $period = RentalPeriod::Monthly,
    BillingMode $mode = BillingMode::Anniversary,
    int $anchorDay = 1,
    ?string $contractEnd = null,
    int $leadDays = 7,
    ProrationBasis $basis = ProrationBasis::ActualDays,
): BillingSchedule {
    return new BillingSchedule(
        new BillingRules($period, $mode, $anchorDay, $basis, $leadDays),
        $contractEnd !== null ? CarbonImmutable::parse($contractEnd) : null,
    );
}

/**
 * @return list<array{0: string, 1: string}>
 */
function periods(BillingSchedule $schedule, string $start, int $count): array
{
    $periods = [];
    $next = CarbonImmutable::parse($start);

    for ($i = 0; $i < $count; $i++) {
        $period = $schedule->periodStartingAt($next) ?? throw new LogicException('Expected a period.');
        $periods[] = [$period->start->toDateString(), $period->end->toDateString()];
        $next = $period->end->addDay();
    }

    return $periods;
}

function periodAt(BillingSchedule $schedule, string $start): BillingPeriod
{
    return $schedule->periodStartingAt(CarbonImmutable::parse($start)) ?? throw new LogicException('Expected a period.');
}

describe('anniversary mode', function () {
    it('bills each month from the contract start date', function () {
        expect(periods(schedule(anchorDay: 20), '2026-09-20', 3))->toBe([
            ['2026-09-20', '2026-10-19'],
            ['2026-10-20', '2026-11-19'],
            ['2026-11-20', '2026-12-19'],
        ]);
    });

    it('falls on the last day of shorter months for starts on the 29th to 31st', function () {
        expect(periods(schedule(anchorDay: 31), '2026-01-31', 4))->toBe([
            ['2026-01-31', '2026-02-27'],
            ['2026-02-28', '2026-03-30'],
            ['2026-03-31', '2026-04-29'],
            ['2026-04-30', '2026-05-30'],
        ]);
    });

    it('uses 29 February in a leap year', function () {
        expect(periods(schedule(anchorDay: 30), '2028-01-30', 2))->toBe([
            ['2028-01-30', '2028-02-28'],
            ['2028-02-29', '2028-03-29'],
        ]);
    });

    it('bills quarterly, half-yearly, and yearly periods', function (RentalPeriod $period, string $start, int $anchorDay, array $expected) {
        expect(periods(schedule($period, anchorDay: $anchorDay), $start, 2))->toBe($expected);
    })->with([
        'quarterly' => [RentalPeriod::Quarterly, '2026-01-31', 31, [['2026-01-31', '2026-04-29'], ['2026-04-30', '2026-07-30']]],
        'half-yearly' => [RentalPeriod::Semiannual, '2026-03-15', 15, [['2026-03-15', '2026-09-14'], ['2026-09-15', '2027-03-14']]],
        'yearly from 29 February' => [RentalPeriod::Yearly, '2028-02-29', 29, [['2028-02-29', '2029-02-27'], ['2029-02-28', '2030-02-27']]],
    ]);

    it('bills weekly and daily periods', function (RentalPeriod $period, array $expected) {
        expect(periods(schedule($period, anchorDay: 16), '2026-09-16', 2))->toBe($expected);
    })->with([
        'weekly' => [RentalPeriod::Weekly, [['2026-09-16', '2026-09-22'], ['2026-09-23', '2026-09-29']]],
        'daily' => [RentalPeriod::Daily, [['2026-09-16', '2026-09-16'], ['2026-09-17', '2026-09-17']]],
    ]);

    it('is due on the first day of each period', function () {
        expect(periodAt(schedule(anchorDay: 20), '2026-10-20')->dueDate->toDateString())->toBe('2026-10-20');
    });
});

describe('fixed date mode', function () {
    it('bills the first days up to the fixed date as a short period', function () {
        expect(periods(schedule(mode: BillingMode::FixedDate, anchorDay: 1), '2026-09-20', 3))->toBe([
            ['2026-09-20', '2026-09-30'],
            ['2026-10-01', '2026-10-31'],
            ['2026-11-01', '2026-11-30'],
        ]);
    });

    it('bills a full month straight away when the contract starts on the fixed date', function () {
        $period = periodAt(schedule(mode: BillingMode::FixedDate, anchorDay: 1), '2026-10-01');

        expect([$period->start->toDateString(), $period->end->toDateString(), $period->isPartial()])
            ->toBe(['2026-10-01', '2026-10-31', false]);
    });

    it('uses the fixed date when it comes later in the same month', function () {
        expect(periods(schedule(mode: BillingMode::FixedDate, anchorDay: 25), '2026-09-20', 2))->toBe([
            ['2026-09-20', '2026-09-24'],
            ['2026-09-25', '2026-10-24'],
        ]);
    });

    it('bills a short first period, then full quarters from the fixed date', function () {
        expect(periods(schedule(RentalPeriod::Quarterly, BillingMode::FixedDate, anchorDay: 5), '2026-09-20', 2))->toBe([
            ['2026-09-20', '2026-10-04'],
            ['2026-10-05', '2027-01-04'],
        ]);
    });
});

describe('contract end', function () {
    it('cuts the last period at the contract end date', function () {
        $period = periodAt(schedule(anchorDay: 20, contractEnd: '2026-11-10'), '2026-10-20');

        expect([$period->end->toDateString(), $period->isPartial(), $period->billedDays, $period->basisDays])
            ->toBe(['2026-11-10', true, 22, 31]);
    });

    it('has no period starting after the contract ends', function () {
        expect(schedule(anchorDay: 20, contractEnd: '2026-11-10')->periodStartingAt(CarbonImmutable::parse('2026-11-11')))->toBeNull();
    });
});

describe('issue date', function () {
    it('issues invoices the lead days before the due date', function () {
        expect(periodAt(schedule(anchorDay: 20, leadDays: 7), '2026-10-20')->issueDate->toDateString())->toBe('2026-10-13');
    });

    it('never issues earlier than the period before it started', function (RentalPeriod $period, string $expected) {
        expect(periodAt(schedule($period, anchorDay: 16, leadDays: 7), '2026-09-16')->issueDate->toDateString())->toBe($expected);
    })->with([
        'weekly, one day after the previous week started' => [RentalPeriod::Weekly, '2026-09-10'],
        'daily, on the day itself' => [RentalPeriod::Daily, '2026-09-16'],
    ]);
});
