<?php

use App\Modules\Payment\Engine\AllocationLine;
use App\Modules\Payment\Engine\OpenInvoice;
use App\Modules\Payment\Engine\PaymentAllocator;
use App\Modules\Property\Enums\AllocationCategory as Category;
use Carbon\CarbonImmutable;

/*
 * Payment allocation rules (PRD §8.5, docs/adr/0008-keputusan-mvp.md):
 * oldest invoice first; inside an invoice deposit, rent, utility, addon,
 * other, then penalty unless the property changes the order; manual
 * allocation to chosen invoices; whatever is left becomes credit.
 */

/**
 * @param  array<string, int>  $outstanding  What is still owed per category value
 */
function openInvoice(string $id, string $dueDate, array $outstanding): OpenInvoice
{
    return new OpenInvoice($id, CarbonImmutable::parse($dueDate), $outstanding);
}

/**
 * @param  list<AllocationLine>  $lines
 * @return list<array{0: string, 1: string, 2: int}>
 */
function allocatedLines(array $lines): array
{
    return array_map(fn (AllocationLine $line): array => [$line->invoiceId, $line->category->value, $line->amount], $lines);
}

/**
 * @return list<Category>
 */
function categoryOrder(): array
{
    return Category::cases();
}

it('pays the oldest invoice first, whatever order the invoices come in', function () {
    $plan = PaymentAllocator::allocate(1_500_000, [
        openInvoice('B', '2026-10-20', ['rent' => 1_200_000]),
        openInvoice('A', '2026-09-20', ['rent' => 1_200_000]),
    ], categoryOrder());

    expect(allocatedLines($plan->lines))->toBe([
        ['A', 'rent', 1_200_000],
        ['B', 'rent', 300_000],
    ])->and($plan->remainder)->toBe(0);
});

it('orders invoices due the same day by id', function () {
    $plan = PaymentAllocator::allocate(100, [
        openInvoice('02', '2026-09-20', ['other' => 100]),
        openInvoice('01', '2026-09-20', ['other' => 100]),
    ], categoryOrder());

    expect(allocatedLines($plan->lines))->toBe([['01', 'other', 100]]);
});

it('fills deposit, rent, utility, addon, other, and penalty in that order by default', function () {
    $plan = PaymentAllocator::allocate(2_000_000, [
        openInvoice('A', '2026-09-20', [
            'penalty' => 50_000,
            'utility' => 150_000,
            'rent' => 1_200_000,
            'other' => 25_000,
            'deposit' => 500_000,
            'addon' => 40_000,
        ]),
    ], categoryOrder());

    expect(allocatedLines($plan->lines))->toBe([
        ['A', 'deposit', 500_000],
        ['A', 'rent', 1_200_000],
        ['A', 'utility', 150_000],
        ['A', 'addon', 40_000],
        ['A', 'other', 25_000],
        ['A', 'penalty', 50_000],
    ])->and($plan->remainder)->toBe(35_000);
});

it('leaves the penalty unpaid when a partial payment runs out', function () {
    $plan = PaymentAllocator::allocate(1_300_000, [
        openInvoice('A', '2026-09-20', ['rent' => 1_200_000, 'utility' => 150_000, 'penalty' => 50_000]),
    ], categoryOrder());

    expect(allocatedLines($plan->lines))->toBe([
        ['A', 'rent', 1_200_000],
        ['A', 'utility', 100_000],
    ])->and($plan->remainder)->toBe(0)
        ->and($plan->allocatedTo('A'))->toBe(1_300_000);
});

it('follows the order set for the property', function () {
    $plan = PaymentAllocator::allocate(100_000, [
        openInvoice('A', '2026-09-20', ['rent' => 1_200_000, 'penalty' => 50_000, 'utility' => 30_000]),
    ], [Category::Penalty, Category::Utility, Category::Rent, Category::Deposit, Category::Addon, Category::Other]);

    expect(allocatedLines($plan->lines))->toBe([
        ['A', 'penalty', 50_000],
        ['A', 'utility', 30_000],
        ['A', 'rent', 20_000],
    ]);
});

it('finishes one invoice before touching the next', function () {
    $plan = PaymentAllocator::allocate(1_300_000, [
        openInvoice('A', '2026-09-20', ['rent' => 1_000_000, 'penalty' => 100_000]),
        openInvoice('B', '2026-10-20', ['deposit' => 500_000, 'rent' => 1_000_000]),
    ], categoryOrder());

    expect(allocatedLines($plan->lines))->toBe([
        ['A', 'rent', 1_000_000],
        ['A', 'penalty', 100_000],
        ['B', 'deposit', 200_000],
    ]);
});

it('turns what is left after every open invoice into credit', function () {
    $plan = PaymentAllocator::allocate(1_500_000, [
        openInvoice('A', '2026-09-20', ['rent' => 1_200_000]),
    ], categoryOrder());

    expect($plan->remainder)->toBe(300_000)
        ->and($plan->allocated())->toBe(1_200_000);
});

it('keeps everything as credit when nothing is open', function () {
    $plan = PaymentAllocator::allocate(250_000, [], categoryOrder());

    expect($plan->lines)->toBe([])->and($plan->remainder)->toBe(250_000);
});

it('never pays more than an invoice still owes when a discount or rounding makes a component negative', function () {
    $plan = PaymentAllocator::allocate(2_000_000, [
        openInvoice('A', '2026-09-20', ['rent' => 1_200_000, 'other' => -400]),
    ], categoryOrder());

    expect(allocatedLines($plan->lines))->toBe([['A', 'rent', 1_199_600]])
        ->and($plan->remainder)->toBe(800_400);
});

it('skips invoices that owe nothing', function () {
    $plan = PaymentAllocator::allocate(100_000, [
        openInvoice('A', '2026-09-20', ['rent' => 0]),
        openInvoice('B', '2026-10-20', ['rent' => 100_000]),
    ], categoryOrder());

    expect(allocatedLines($plan->lines))->toBe([['B', 'rent', 100_000]]);
});

it('leaves out the categories a source may not pay', function () {
    $plan = PaymentAllocator::allocate(1_000_000, [
        openInvoice('A', '2026-09-20', ['deposit' => 500_000, 'rent' => 600_000]),
    ], categoryOrder(), except: [Category::Deposit]);

    expect(allocatedLines($plan->lines))->toBe([['A', 'rent', 600_000]])
        ->and($plan->remainder)->toBe(400_000);
});

describe('manual allocation', function () {
    it('pays the chosen invoices with the chosen amounts, in category order inside each', function () {
        $plan = PaymentAllocator::allocateTo(1_500_000, [
            openInvoice('A', '2026-09-20', ['rent' => 1_200_000]),
            openInvoice('B', '2026-10-20', ['rent' => 1_200_000, 'utility' => 100_000]),
        ], ['B' => 1_250_000], categoryOrder());

        expect(allocatedLines($plan->lines))->toBe([
            ['B', 'rent', 1_200_000],
            ['B', 'utility', 50_000],
        ])->and($plan->remainder)->toBe(250_000);
    });

    it('refuses more than an invoice owes', function () {
        PaymentAllocator::allocateTo(1_500_000, [
            openInvoice('A', '2026-09-20', ['rent' => 1_200_000]),
        ], ['A' => 1_300_000], categoryOrder());
    })->throws(InvalidArgumentException::class, 'A');

    it('refuses an invoice that is not open', function () {
        PaymentAllocator::allocateTo(100_000, [], ['X' => 100_000], categoryOrder());
    })->throws(InvalidArgumentException::class, 'X');

    it('refuses to hand out more than the payment', function () {
        PaymentAllocator::allocateTo(100_000, [
            openInvoice('A', '2026-09-20', ['rent' => 1_200_000]),
        ], ['A' => 150_000], categoryOrder());
    })->throws(InvalidArgumentException::class);
});
