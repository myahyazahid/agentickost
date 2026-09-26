<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Billing\Actions\AccrueInvoicePenalties;
use App\Modules\Billing\Actions\WaivePenalties;
use App\Modules\Billing\Events\PenaltyAccrued;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PenaltyAccrual;
use App\Modules\Property\Models\Property;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    $this->travelTo('2026-09-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->contract = LeaseScenario::active(overrides: ['deposit_amount' => 0]);
    $this->property = Property::query()->findOrFail($this->contract->property_id);
});

/**
 * @return list<string>
 */
function accruedDays(Invoice $invoice): array
{
    return array_values($invoice->penalties()->orderBy('accrued_on')->get()->map(fn (PenaltyAccrual $penalty): string => $penalty->accrued_on->toDateString())->all());
}

it('charges a daily penalty from the day after the grace period, up to the cap', function () {
    BillingScenario::settings($this->property, ['penalty_type' => 'daily', 'penalty_amount' => 10_000, 'penalty_max_amount' => 35_000, 'grace_days' => 3]);
    [$invoice] = BillingScenario::issueDue($this->contract);

    $this->travelTo('2026-09-23 03:00:00');
    expect(app(AccrueInvoicePenalties::class)->handle($invoice))->toBe([]);

    $this->travelTo('2026-09-25 03:00:00');
    app(AccrueInvoicePenalties::class)->handle($invoice);
    app(AccrueInvoicePenalties::class)->handle($invoice);

    expect(accruedDays($invoice))->toBe(['2026-09-24', '2026-09-25'])
        ->and($invoice->refresh()->penalty_amount)->toBe(20_000)
        ->and($invoice->balance_amount)->toBe(1_220_000)
        ->and($invoice->items()->count())->toBe(1);

    $this->travelTo('2026-10-05 03:00:00');
    app(AccrueInvoicePenalties::class)->handle($invoice);

    expect($invoice->refresh()->penalty_amount)->toBe(35_000)
        ->and(PenaltyAccrual::query()->pluck('amount')->all())->toBe([10_000, 10_000, 10_000, 5_000]);
});

it('charges a flat or percent penalty once', function (array $settings, int $expected) {
    BillingScenario::settings($this->property, $settings);
    [$invoice] = BillingScenario::issueDue($this->contract);
    $this->travelTo('2026-10-01 03:00:00');

    app(AccrueInvoicePenalties::class)->handle($invoice);
    $this->travelTo('2026-10-03 03:00:00');
    app(AccrueInvoicePenalties::class)->handle($invoice);

    expect(accruedDays($invoice))->toBe(['2026-09-24'])
        ->and($invoice->refresh()->penalty_amount)->toBe($expected);
})->with([
    'flat Rp50.000' => [['penalty_type' => 'flat', 'penalty_amount' => 50_000], 50_000],
    '2,5% of Rp1.200.000' => [['penalty_type' => 'percent', 'penalty_percent' => '2.50'], 30_000],
]);

it('charges nothing when the property has no penalty or the invoice is paid', function () {
    [$invoice] = BillingScenario::issueDue($this->contract);
    $this->travelTo('2026-10-01 03:00:00');

    expect(app(AccrueInvoicePenalties::class)->handle($invoice))->toBe([]);

    BillingScenario::settings($this->property, ['penalty_type' => 'daily', 'penalty_amount' => 10_000]);
    $invoice->paid_amount = 1_200_000;
    $invoice->save();

    expect(app(AccrueInvoicePenalties::class)->handle($invoice->refresh()))->toBe([]);
});

it('accrues penalties of every tenant from the scheduled command', function () {
    Event::fake([PenaltyAccrued::class]);
    BillingScenario::settings($this->property, ['penalty_type' => 'flat', 'penalty_amount' => 50_000]);
    [$invoice] = BillingScenario::issueDue($this->contract);
    tenancy()->forget();
    actors()->forget();
    $this->travelTo('2026-09-24 03:00:00');

    $this->artisan('billing:accrue-penalties')->expectsOutputToContain('1 denda dicatat.')->assertSuccessful();

    Event::assertDispatchedTimes(PenaltyAccrued::class, 1);
});

it('shows an invoice as late only after its due date while unpaid', function () {
    [$invoice] = BillingScenario::issueDue($this->contract);

    expect($invoice->isOverdue())->toBeFalse();

    $this->travelTo('2026-09-21 03:00:00');
    expect($invoice->refresh()->isOverdue())->toBeTrue();

    $invoice->paid_amount = 1_200_000;
    $invoice->save();
    expect($invoice->refresh()->isOverdue())->toBeFalse();
});

it('waives penalties with a reason in the audit log, and never charges those days again', function () {
    BillingScenario::settings($this->property, ['penalty_type' => 'daily', 'penalty_amount' => 10_000]);
    [$invoice] = BillingScenario::issueDue($this->contract);
    $this->travelTo('2026-09-25 03:00:00');
    app(AccrueInvoicePenalties::class)->handle($invoice);

    app(WaivePenalties::class)->handle($invoice, ['reason' => 'Transfer tertahan di bank']);
    app(AccrueInvoicePenalties::class)->handle($invoice);

    $log = AuditLog::query()->where('event', 'penalty.waived')->sole();
    expect($invoice->refresh()->penalty_amount)->toBe(0)
        ->and(PenaltyAccrual::query()->whereNull('waived_at')->count())->toBe(0)
        ->and($log->reason)->toBe('Transfer tertahan di bank')
        ->and($log->old_values)->toBe(['penalty_amount' => 20_000])
        ->and($log->actor_id)->toBe($this->owner->id);

    $this->travelTo('2026-09-26 03:00:00');
    app(AccrueInvoicePenalties::class)->handle($invoice);

    expect(accruedDays($invoice))->toBe(['2026-09-24', '2026-09-25', '2026-09-26'])
        ->and($invoice->refresh()->penalty_amount)->toBe(10_000);
});

it('needs a reason and the owner role to waive', function () {
    BillingScenario::settings($this->property, ['penalty_type' => 'flat', 'penalty_amount' => 50_000]);
    [$invoice] = BillingScenario::issueDue($this->contract);
    $this->travelTo('2026-09-25 03:00:00');
    app(AccrueInvoicePenalties::class)->handle($invoice);

    expect(fn () => app(WaivePenalties::class)->handle($invoice, ['reason' => '']))->toThrow(ValidationException::class);

    loginAs(staff(Role::Manager, $this->owner->tenant()->firstOrFail()));

    app(WaivePenalties::class)->handle($invoice, ['reason' => 'Penghuni lama']);
})->throws(AuthorizationException::class);
