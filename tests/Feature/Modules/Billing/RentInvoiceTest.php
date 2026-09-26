<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Events\InvoiceIssued;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Issued;
use App\Modules\Lease\Actions\ActivateContract;
use App\Modules\Lease\Actions\AddContractHold;
use App\Modules\Lease\Actions\GiveNotice;
use App\Modules\Lease\Actions\RenewContract;
use App\Modules\Property\Models\Property;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    $this->travelTo('2026-09-15 03:00:00');
    loginAs(staff(Role::Owner));
});

it('issues the first invoice with the rent and the deposit, due on the start date', function () {
    Event::fake([InvoiceIssued::class]);
    $contract = LeaseScenario::active();

    [$invoice] = BillingScenario::issueDue($contract);

    expect($invoice->status)->toBeInstanceOf(Issued::class)
        ->and($invoice->number)->toBe('INV/2026/09/0001')
        ->and($invoice->issue_date->toDateString())->toBe('2026-09-15')
        ->and($invoice->due_date->toDateString())->toBe('2026-09-20')
        ->and($invoice->period_start->toDateString())->toBe('2026-09-20')
        ->and($invoice->period_end->toDateString())->toBe('2026-10-19')
        ->and(BillingScenario::lines($invoice))->toBe([['rent', 1_200_000], ['deposit', 1_200_000]])
        ->and($invoice->items_total_amount)->toBe(2_400_000)
        ->and($invoice->refresh()->balance_amount)->toBe(2_400_000)
        ->and($contract->refresh()->next_period_start->toDateString())->toBe('2026-10-20');
    Event::assertDispatched(InvoiceIssued::class, fn (InvoiceIssued $event): bool => $event->invoice->is($invoice));
});

it('waits for the issue date, lead days before the period starts', function () {
    $contract = LeaseScenario::active(overrides: ['start_date' => '2026-09-25']);

    expect(BillingScenario::issueDue($contract))->toBe([]);

    $this->travelTo('2026-09-18 03:00:00');
    expect(BillingScenario::issueDue($contract))->toHaveCount(1);
});

it('bills each period once however often it runs', function () {
    $contract = LeaseScenario::active();

    BillingScenario::issueDue($contract);
    BillingScenario::issueDue($contract);
    $this->travelTo('2026-10-13 03:00:00');
    BillingScenario::issueDue($contract);
    BillingScenario::issueDue($contract);

    expect(Invoice::query()->where('contract_id', $contract->id)->pluck('period_start')->map->toDateString()->all())
        ->toBe(['2026-09-20', '2026-10-20']);
});

it('catches up on every missed period, the deposit only on the first', function () {
    $contract = LeaseScenario::active();
    $this->travelTo('2026-12-01 03:00:00');

    $invoices = BillingScenario::issueDue($contract);

    expect($invoices)->toHaveCount(3)
        ->and(array_map(BillingScenario::lines(...), $invoices))->toBe([
            [['rent', 1_200_000], ['deposit', 1_200_000]],
            [['rent', 1_200_000]],
            [['rent', 1_200_000]],
        ]);
});

it('prorates the first period in fixed date mode, as in the PRD example', function () {
    $room = LeaseScenario::room();
    BillingScenario::settings($room->property()->firstOrFail(), ['billing_mode' => 'fixed_date', 'fixed_billing_day' => 1]);
    $contract = LeaseScenario::active($room, ['deposit_amount' => 0]);

    [$invoice] = BillingScenario::issueDue($contract);

    expect(BillingScenario::lines($invoice))->toBe([['rent', 440_000]])
        ->and($invoice->period_end->toDateString())->toBe('2026-09-30')
        ->and($invoice->items()->firstOrFail()->description)->toContain('prorata 11/30 hari');
});

it('rounds the total on its own line when the property rounds', function () {
    $room = LeaseScenario::room();
    BillingScenario::settings($room->property()->firstOrFail(), ['billing_mode' => 'fixed_date', 'fixed_billing_day' => 1, 'rounding_unit' => 1_000]);
    $contract = LeaseScenario::active($room, ['rent_amount' => 1_150_000, 'deposit_amount' => 0, 'start_date' => '2026-09-18']);

    [$invoice] = BillingScenario::issueDue($contract);

    // 1.150.000 x 13/30 = 498.333
    expect(BillingScenario::lines($invoice))->toBe([['rent', 498_333], ['rounding', -333]])
        ->and($invoice->items_total_amount)->toBe(498_000);
});

it('charges the hold rate for a period that starts inside a hold', function () {
    $contract = LeaseScenario::active();
    app(AddContractHold::class)->handle($contract, [
        'start_date' => '2026-10-20', 'end_date' => '2026-11-19', 'rent_amount' => 400_000, 'reason' => 'Libur semester',
    ]);
    $this->travelTo('2026-11-13 03:00:00');

    $invoices = BillingScenario::issueDue($contract);

    expect(array_map(fn (Invoice $invoice): int => (int) $invoice->items()->where('type', 'rent')->sum('amount'), $invoices))
        ->toBe([1_200_000, 400_000, 1_200_000]);
});

it('stops at the end date, with a short last period', function () {
    $contract = LeaseScenario::active(overrides: ['end_date' => '2026-11-05']);
    $this->travelTo('2027-01-01 03:00:00');

    $invoices = BillingScenario::issueDue($contract);

    expect($invoices)->toHaveCount(2)
        ->and($invoices[1]->period_end->toDateString())->toBe('2026-11-05')
        // 1.200.000 x 17/31 days of 20 Oct to 19 Nov
        ->and(BillingScenario::lines($invoices[1]))->toBe([['rent', 658_065]]);
});

it('stops at the planned move-out after a notice', function () {
    $contract = LeaseScenario::active();
    BillingScenario::issueDue($contract);
    app(GiveNotice::class)->handle($contract, ['planned_move_out_on' => '2026-10-29']);
    $this->travelTo('2026-12-01 03:00:00');

    $invoices = BillingScenario::issueDue($contract);

    expect($invoices)->toHaveCount(1)
        ->and($invoices[0]->period_end->toDateString())->toBe('2026-10-29');
});

it('bills a renewal only for the deposit it adds', function () {
    $contract = LeaseScenario::active(overrides: ['end_date' => '2026-10-19']);
    BillingScenario::issueDue($contract);
    $renewal = app(RenewContract::class)->handle($contract, ['rent_amount' => 1_300_000, 'deposit_amount' => 1_300_000]);
    $this->travelTo('2026-10-20 03:00:00');
    app(ActivateContract::class)->handle($renewal);

    [$invoice] = BillingScenario::issueDue($renewal);

    expect(BillingScenario::lines($invoice))->toBe([['rent', 1_300_000], ['deposit', 100_000]])
        ->and(BillingScenario::issueDue($contract))->toBe([]);
});

it('bills weekly and daily contracts per period', function (string $period, string $end, int $count) {
    $contract = LeaseScenario::active(overrides: [
        'rental_period' => $period, 'rent_amount' => 100_000, 'deposit_amount' => 0,
        'start_date' => '2026-09-20', 'end_date' => $end,
    ]);
    $this->travelTo('2026-10-10 03:00:00');

    $invoices = BillingScenario::issueDue($contract);

    expect($invoices)->toHaveCount($count)
        ->and(collect($invoices)->every(fn (Invoice $invoice): bool => BillingScenario::lines($invoice) === [['rent', 100_000]]))->toBeTrue();
})->with([
    'weekly, 20 Sep to 10 Oct' => ['weekly', '2026-10-10', 3],
    'daily, 20 to 24 Sep' => ['daily', '2026-09-24', 5],
]);

it('skips draft contracts', function () {
    expect(BillingScenario::issueDue(LeaseScenario::draft()))->toBe([]);
});

it('keeps billing to the staff who may manage invoices', function () {
    $contract = LeaseScenario::active();
    loginAs(staff(Role::Caretaker, $contract->tenant()->firstOrFail()));

    BillingScenario::issueDue($contract);
})->throws(AuthorizationException::class);

it('issues due invoices of every tenant from the scheduled command', function () {
    $first = LeaseScenario::active();
    tenancy()->forget();
    loginAs(staff(Role::Owner));
    $second = LeaseScenario::active();
    tenancy()->forget();
    auth()->logout();

    $this->artisan('billing:issue-invoices')->expectsOutputToContain('2 tagihan diterbitkan.')->assertSuccessful();

    expect(Invoice::withoutGlobalScopes()->whereIn('contract_id', [$first->id, $second->id])->count())->toBe(2);
});

it('uses the property time zone to decide the issue date', function () {
    $contract = LeaseScenario::active(overrides: ['start_date' => '2026-09-27']);
    Property::query()->whereKey($contract->property_id)->update(['timezone' => 'Asia/Jayapura']);

    // 19 Sep 16:00 UTC is still the 19th in Jakarta but already the 20th in Jayapura.
    $this->travelTo('2026-09-19 16:00:00');

    expect(BillingScenario::issueDue($contract))->toHaveCount(1);
});
