<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Actions\DeleteMeterReading;
use App\Modules\Billing\Actions\SetUtilityRate;
use App\Modules\Billing\Models\MeterReading;
use App\Modules\Billing\Models\UtilityRate;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Property\Actions\AssignStaffToProperty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    Storage::fake();
    $this->travelTo('2026-09-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->room = LeaseScenario::room();
    $this->property = $this->room->property()->firstOrFail();
});

it('closes the previous rate the day before a new one starts', function () {
    $old = BillingScenario::meteredElectricity($this->property, 1_500, '2026-01-01');

    $new = BillingScenario::meteredElectricity($this->property, 1_700, '2026-10-01');

    expect($old->refresh()->effective_until?->toDateString())->toBe('2026-09-30')
        ->and(UtilityRate::inForce($this->property->id, $new->utility, now()->setDate(2026, 9, 30))?->rate_amount)->toBe(1_500)
        ->and(UtilityRate::inForce($this->property->id, $new->utility, now()->setDate(2026, 10, 1))?->rate_amount)->toBe(1_700)
        ->and(fn () => BillingScenario::meteredElectricity($this->property, 1_800, '2026-09-01'))
        ->toThrow(ValidationException::class, 'Sudah ada tarif');
});

it('needs a unit for a metered rate and no amount for tokens', function () {
    expect(fn () => app(SetUtilityRate::class)->handle($this->property, [
        'utility' => 'electricity', 'mode' => 'metered', 'rate_amount' => 1_500, 'effective_from' => '2026-01-01',
    ]))->toThrow(ValidationException::class);

    $token = app(SetUtilityRate::class)->handle($this->property, [
        'utility' => 'electricity', 'mode' => 'token', 'effective_from' => '2026-01-01',
    ]);

    expect($token->rate_amount)->toBe(0)->and($token->unit)->toBeNull();
});

it('keeps rate changes to the owner', function () {
    loginAs(staff(Role::Manager, $this->owner->tenant()->firstOrFail()));

    BillingScenario::meteredElectricity($this->property);
})->throws(AuthorizationException::class);

it('starts from the first reading, then charges usage at the rate of the reading date', function () {
    BillingScenario::meteredElectricity($this->property, 1_500);

    $baseline = BillingScenario::reading($this->room, '2026-08-20', '1200.5');
    $next = BillingScenario::reading($this->room, '2026-09-15', '1325.75');

    expect($baseline->amount)->toBe(0)
        ->and($baseline->previous_value)->toBe('1200.50')
        ->and($next->previous_value)->toBe('1200.50')
        ->and($next->refresh()->usage)->toBe('125.25')
        ->and($next->rate_amount)->toBe(1_500)
        // 125,25 kWh x Rp1.500 = Rp187.875
        ->and($next->amount)->toBe(187_875)
        ->and($next->attachmentPaths(AttachmentCollection::Meter))->toHaveCount(1);
});

it('refuses a number lower than the last one unless the meter was replaced', function () {
    BillingScenario::meteredElectricity($this->property);
    BillingScenario::reading($this->room, '2026-08-20', 1_200);

    expect(fn () => BillingScenario::reading($this->room, '2026-09-15', 1_100))
        ->toThrow(ValidationException::class, 'tidak boleh lebih kecil dari catatan sebelumnya (1.200)');

    $replaced = BillingScenario::reading($this->room, '2026-09-15', 40, ['is_meter_replaced' => true, 'new_meter_start_value' => 0]);

    expect($replaced->refresh()->usage)->toBe('40.00')
        ->and($replaced->amount)->toBe(60_000);
});

it('refuses readings the property does not meter, out of order, in the future, or without a photo', function (array $input, string $message) {
    BillingScenario::meteredElectricity($this->property);
    BillingScenario::reading($this->room, '2026-09-10', 1_000);

    expect(fn () => BillingScenario::reading($this->room, $input['reading_date'] ?? '2026-09-14', 1_100, $input))
        ->toThrow(ValidationException::class, $message);
})->with([
    'water is not metered' => [['utility' => 'water'], 'tidak memakai meteran'],
    'before the last reading' => [['reading_date' => '2026-09-05'], 'tanggal ini atau sesudahnya'],
    'tomorrow' => [['reading_date' => '2026-09-16'], 'masa depan'],
    'no photo' => [['photos' => []], 'photos'],
]);

it('records a reading sent twice from a weak connection only once', function () {
    BillingScenario::meteredElectricity($this->property);
    BillingScenario::reading($this->room, '2026-08-20', 1_000);
    $uuid = '5f0c7f53-1b9a-4c7e-9d7e-3a3c1d3f8a10';

    $first = BillingScenario::reading($this->room, '2026-09-15', 1_050, ['client_uuid' => $uuid]);
    $again = BillingScenario::reading($this->room, '2026-09-15', 1_050, ['client_uuid' => $uuid]);

    expect($again->id)->toBe($first->id)
        ->and(MeterReading::query()->count())->toBe(2);
});

it('lets the caretaker record readings only in assigned properties', function () {
    BillingScenario::meteredElectricity($this->property);
    $caretaker = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    loginAs($caretaker);

    expect(fn () => BillingScenario::reading($this->room, '2026-09-15', 1_000))->toThrow(AuthorizationException::class);

    loginAs($this->owner);
    app(AssignStaffToProperty::class)->handle($this->property, $caretaker);
    loginAs($caretaker);

    expect(BillingScenario::reading($this->room, '2026-09-15', 1_000)->recorded_by)->toBe($caretaker->id);
});

it('deletes only the latest reading, before it is billed', function () {
    BillingScenario::meteredElectricity($this->property);
    $first = BillingScenario::reading($this->room, '2026-08-20', 1_000);
    $latest = BillingScenario::reading($this->room, '2026-09-10', 1_100);

    expect(fn () => app(DeleteMeterReading::class)->handle($first))->toThrow(ValidationException::class, 'terakhir');

    app(DeleteMeterReading::class)->handle($latest);

    expect(MeterReading::query()->pluck('id')->all())->toBe([$first->id]);
});

it('bills unbilled readings and the flat fee on the rent invoice, never twice', function () {
    BillingScenario::meteredElectricity($this->property, 1_500);
    BillingScenario::flatWater($this->property, 50_000);
    BillingScenario::reading($this->room, '2026-09-14', 1_000);
    $contract = LeaseScenario::active($this->room, ['deposit_amount' => 0]);
    [$first] = BillingScenario::issueDue($contract);

    $this->travelTo('2026-10-12 03:00:00');
    $reading = BillingScenario::reading($this->room, '2026-10-12', 1_100);
    $this->travelTo('2026-10-13 03:00:00');
    [$second] = BillingScenario::issueDue($contract);

    expect(BillingScenario::lines($first))->toBe([['rent', 1_200_000], ['utility', 50_000]])
        ->and(BillingScenario::lines($second))->toBe([['rent', 1_200_000], ['utility', 150_000], ['utility', 50_000]])
        ->and($reading->refresh()->isBilled())->toBeTrue()
        ->and(fn () => app(DeleteMeterReading::class)->handle($reading))->toThrow(AuthorizationException::class);
});

it('does not bill a reading taken on move-in day to the new resident', function () {
    $this->travelTo('2026-09-20 03:00:00');
    BillingScenario::meteredElectricity($this->property, 1_500);
    BillingScenario::reading($this->room, '2026-08-01', 900);
    BillingScenario::reading($this->room, '2026-09-20', 1_000);
    $contract = LeaseScenario::active($this->room, ['deposit_amount' => 0]);

    [$invoice] = BillingScenario::issueDue($contract);

    expect(BillingScenario::lines($invoice))->toBe([['rent', 1_200_000]]);
});

it('prorates the flat fee with the rent', function () {
    BillingScenario::settings($this->property, ['billing_mode' => 'fixed_date', 'fixed_billing_day' => 1]);
    BillingScenario::flatWater($this->property, 60_000);
    $contract = LeaseScenario::active($this->room, ['deposit_amount' => 0]);

    [$invoice] = BillingScenario::issueDue($contract);

    // 11 of 30 days
    expect(BillingScenario::lines($invoice))->toBe([['rent', 440_000], ['utility', 22_000]]);
});
