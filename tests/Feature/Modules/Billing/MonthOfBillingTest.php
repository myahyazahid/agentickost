<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Issued;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomType;
use App\Support\Actors\ActorType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;

/**
 * Roadmap M1.3 done criterion: a month of invoices for one property comes
 * out right with no manual correction. The scheduled command runs every
 * morning from 24 September to 31 October against a property with monthly,
 * weekly, month-end and early-ending contracts, a flat water fee, and a
 * metered electricity reading.
 */
it('issues a month of invoices for one property without manual correction', function () {
    Storage::fake();
    $this->travelTo('2026-09-24 00:30:00');
    loginAs(staff(Role::Owner));

    $type = RoomType::factory()->create(['default_capacity' => 1]);
    [$roomA, $roomB, $roomC, $roomD] = Room::factory()->forType($type)->count(4)->create(['capacity' => 1])->all();
    $property = $type->property()->firstOrFail();
    BillingScenario::meteredElectricity($property, 1_500);
    BillingScenario::flatWater($property, 40_000);

    $contracts = [
        'A' => LeaseScenario::active($roomA, ['start_date' => '2026-10-01', 'rent_amount' => 1_000_000, 'deposit_amount' => 1_000_000]),
        'B' => LeaseScenario::active($roomB, ['start_date' => '2026-10-31', 'rent_amount' => 900_000, 'deposit_amount' => 0]),
        'C' => LeaseScenario::active($roomC, [
            'rental_period' => 'weekly', 'start_date' => '2026-10-05', 'end_date' => '2026-10-25',
            'rent_amount' => 300_000, 'deposit_amount' => 0,
        ]),
        'D' => LeaseScenario::active($roomD, [
            'start_date' => '2026-10-10', 'end_date' => '2026-11-05', 'rent_amount' => 1_000_000, 'deposit_amount' => 0,
        ]),
    ];
    $labels = array_flip(array_map(fn ($contract) => $contract->id, $contracts));

    tenancy()->forget();
    actors()->forget();
    auth()->logout();

    $readings = [
        '2026-09-30' => 500,
        '2026-10-20' => 620,
    ];

    for ($day = CarbonImmutable::parse('2026-09-24'); $day->lessThanOrEqualTo(CarbonImmutable::parse('2026-10-31')); $day = $day->addDay()) {
        $this->travelTo($day->setTime(1, 0));

        if (isset($readings[$day->toDateString()])) {
            loginAs(staff(Role::Owner, $property->tenant()->firstOrFail()));
            BillingScenario::reading($roomA, $day->toDateString(), $readings[$day->toDateString()]);
            tenancy()->forget();
            actors()->forget();
            auth()->logout();
        }

        $this->artisan('billing:issue-invoices')->assertSuccessful();
        $this->artisan('billing:issue-invoices')->assertSuccessful();
    }

    $issued = Invoice::withoutGlobalScopes()->orderBy('number')->get()->map(fn (Invoice $invoice): array => [
        $invoice->number,
        $labels[$invoice->contract_id],
        $invoice->issue_date?->toDateString(),
        $invoice->period_start?->toDateString().' - '.$invoice->period_end?->toDateString(),
        $invoice->due_date->toDateString(),
        array_values($invoice->items()->withoutGlobalScopes()->get()->map(fn ($item): array => [$item->type->value, $item->amount])->all()),
        $invoice->items_total_amount,
    ])->all();

    expect($issued)->toBe([
        ['INV/2026/09/0001', 'A', '2026-09-24', '2026-10-01 - 2026-10-31', '2026-10-01', [['rent', 1_000_000], ['deposit', 1_000_000], ['utility', 40_000]], 2_040_000],
        ['INV/2026/09/0002', 'C', '2026-09-29', '2026-10-05 - 2026-10-11', '2026-10-05', [['rent', 300_000]], 300_000],
        // Ends on 5 Nov: 27 of 31 days, water prorated the same way.
        ['INV/2026/10/0001', 'D', '2026-10-03', '2026-10-10 - 2026-11-05', '2026-10-10', [['rent', 870_968], ['utility', 34_839]], 905_807],
        ['INV/2026/10/0002', 'C', '2026-10-06', '2026-10-12 - 2026-10-18', '2026-10-12', [['rent', 300_000]], 300_000],
        ['INV/2026/10/0003', 'C', '2026-10-13', '2026-10-19 - 2026-10-25', '2026-10-19', [['rent', 300_000]], 300_000],
        // Starts on the 31st: November has 30 days, so the period ends on the 29th.
        ['INV/2026/10/0004', 'B', '2026-10-24', '2026-10-31 - 2026-11-29', '2026-10-31', [['rent', 900_000], ['utility', 40_000]], 940_000],
        // 120 kWh read on 20 Oct at Rp1.500.
        ['INV/2026/10/0005', 'A', '2026-10-25', '2026-11-01 - 2026-11-30', '2026-11-01', [['rent', 1_000_000], ['utility', 180_000], ['utility', 40_000]], 1_220_000],
    ]);

    expect(Invoice::withoutGlobalScopes()->get()->every(
        fn (Invoice $invoice): bool => $invoice->status->equals(Issued::class) && $invoice->issued_by_type === ActorType::System,
    ))->toBeTrue();
});
