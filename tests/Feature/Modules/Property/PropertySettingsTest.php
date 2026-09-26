<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Property\Actions\CreateProperty;
use App\Modules\Property\Actions\UpdatePropertySettings;
use App\Modules\Property\Enums\BillingMode;
use App\Modules\Property\Enums\PenaltyType;
use App\Modules\Property\Enums\ProrationBasis;
use App\Modules\Property\Models\Property;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingsInput(array $overrides = []): array
{
    return [
        'billing_mode' => 'anniversary',
        'fixed_billing_day' => null,
        'invoice_lead_days' => 7,
        'proration_basis' => 'actual_days',
        'grace_days' => 3,
        'penalty_type' => 'none',
        'penalty_amount' => null,
        'penalty_percent' => null,
        'penalty_max_amount' => null,
        'allocation_order' => ['deposit', 'rent', 'utility', 'addon', 'other', 'penalty'],
        'notice_days' => 30,
        'rounding_unit' => 1,
        ...$overrides,
    ];
}

it('gives a new property the default billing rules', function () {
    loginAs(staff(Role::Owner));

    $property = app(CreateProperty::class)->handle([
        'name' => 'Kost Melati', 'code' => 'MLT', 'address' => 'Jl. Melati 5', 'city' => 'Bandung',
        'province' => 'Jawa Barat', 'timezone' => 'Asia/Jakarta', 'gender_policy' => 'female',
    ]);

    $settings = $property->resolvedSettings();
    expect($settings->billing_mode)->toBe(BillingMode::Anniversary)
        ->and($settings->invoice_lead_days)->toBe(7)
        ->and($settings->proration_basis)->toBe(ProrationBasis::ActualDays)
        ->and($settings->grace_days)->toBe(3)
        ->and($settings->penalty_type)->toBe(PenaltyType::None)
        ->and($settings->allocation_order)->toBe(['deposit', 'rent', 'utility', 'addon', 'other', 'penalty'])
        ->and($settings->notice_days)->toBe(30);
});

it('saves a fixed billing date with a daily penalty and a cap', function () {
    loginAs(staff(Role::Owner));
    $property = Property::factory()->create();

    $settings = app(UpdatePropertySettings::class)->handle($property, settingsInput([
        'billing_mode' => 'fixed_date',
        'fixed_billing_day' => 5,
        'penalty_type' => 'daily',
        'penalty_amount' => 10_000,
        'penalty_percent' => 2.5,
        'penalty_max_amount' => 100_000,
        'allocation_order' => ['rent', 'deposit', 'utility', 'addon', 'other', 'penalty'],
    ]));

    expect($settings->billing_mode)->toBe(BillingMode::FixedDate)
        ->and($settings->fixed_billing_day)->toBe(5)
        ->and($settings->penalty_amount)->toBe(10_000)
        ->and($settings->penalty_percent)->toBeNull()
        ->and($settings->penalty_max_amount)->toBe(100_000)
        ->and($settings->allocationOrder()[0]->value)->toBe('rent');
});

it('clears values that do not apply to the chosen rules', function () {
    loginAs(staff(Role::Owner));
    $property = Property::factory()->create();

    $settings = app(UpdatePropertySettings::class)->handle($property, settingsInput([
        'fixed_billing_day' => 5,
        'penalty_type' => 'none',
        'penalty_amount' => 10_000,
        'penalty_max_amount' => 100_000,
    ]));

    expect($settings->fixed_billing_day)->toBeNull()
        ->and($settings->penalty_amount)->toBeNull()
        ->and($settings->penalty_max_amount)->toBeNull();
});

it('rejects incomplete settings', function (array $overrides, string $field) {
    loginAs(staff(Role::Owner));
    $property = Property::factory()->create();

    expect(fn () => app(UpdatePropertySettings::class)->handle($property, settingsInput($overrides)))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field));
})->with([
    'fixed date without a day' => [['billing_mode' => 'fixed_date'], 'fixed_billing_day'],
    'day 29 or later' => [['billing_mode' => 'fixed_date', 'fixed_billing_day' => 29], 'fixed_billing_day'],
    'flat penalty without amount' => [['penalty_type' => 'flat'], 'penalty_amount'],
    'percent penalty without percent' => [['penalty_type' => 'percent'], 'penalty_percent'],
    'allocation order missing a component' => [['allocation_order' => ['rent', 'utility', 'addon', 'other', 'penalty']], 'allocation_order'],
    'allocation order with a duplicate' => [['allocation_order' => ['rent', 'rent', 'utility', 'addon', 'other', 'penalty']], 'allocation_order'],
    'rounding to an odd unit' => [['rounding_unit' => 250], 'rounding_unit'],
]);

it('leaves billing rules to the owner', function (Role $role) {
    $owner = staff(Role::Owner);
    $tenant = $owner->tenant()->firstOrFail();
    $property = tenancy()->run($tenant, fn () => Property::factory()->create());
    loginAs(staff($role, $tenant));

    app(UpdatePropertySettings::class)->handle($property, settingsInput());
})->with([Role::Manager, Role::Accountant])->throws(AuthorizationException::class);
