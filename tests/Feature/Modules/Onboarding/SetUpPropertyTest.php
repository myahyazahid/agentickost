<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Onboarding\Actions\SetUpProperty;
use App\Modules\Onboarding\Support\OnboardingProgress;
use App\Modules\Property\Enums\PenaltyType;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomType;
use App\Modules\Property\Support\RoomPricing;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    $this->travelTo('2026-10-05 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function wizardInput(array $overrides = []): array
{
    return [
        'name' => 'Kost Melati',
        'code' => 'MLT',
        'address' => 'Jl. Melati 5',
        'city' => 'Semarang',
        'province' => 'Jawa Tengah',
        'timezone' => 'Asia/Jakarta',
        'gender_policy' => 'mixed',
        'billing_mode' => 'fixed_date',
        'fixed_billing_day' => 5,
        'invoice_lead_days' => 7,
        'proration_basis' => 'actual_days',
        'rounding_unit' => 1000,
        'grace_days' => 3,
        'penalty_type' => 'daily',
        'penalty_amount' => 10_000,
        'penalty_max_amount' => 100_000,
        'room_types' => [
            ['name' => 'Standar', 'default_capacity' => 1, 'rental_period' => 'monthly', 'price' => 900_000, 'room_numbers' => ['101', '102', '103']],
            ['name' => 'AC', 'default_capacity' => 2, 'rental_period' => 'monthly', 'price' => 1_400_000, 'room_numbers' => ['201']],
        ],
        'bank_accounts' => [
            ['kind' => 'bank', 'provider_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Siti Aminah'],
        ],
        ...$overrides,
    ];
}

it('sets up a property with its rules, room types, prices, rooms, and bank account at once', function () {
    $property = app(SetUpProperty::class)->handle(wizardInput());

    $settings = $property->resolvedSettings();
    expect($property->code)->toBe('MLT')
        ->and($settings->fixed_billing_day)->toBe(5)
        ->and($settings->penalty_type)->toBe(PenaltyType::Daily)
        ->and($settings->penalty_amount)->toBe(10_000)
        ->and($settings->notice_days)->toBe(30);

    $standard = RoomType::query()->where('name', 'Standar')->sole();
    expect(RoomPricing::inForce($standard->prices()->getQuery(), RentalPeriod::Monthly, $property->today())?->amount)->toBe(900_000)
        ->and(Room::query()->where('room_type_id', $standard->id)->pluck('number')->sort()->values()->all())->toBe(['101', '102', '103'])
        ->and(Room::query()->where('number', '201')->sole()->capacity)->toBe(2);

    expect(BankAccount::query()->sole()->is_default)->toBeTrue();
});

it('saves nothing and points at the wizard field when a part fails', function (array $overrides, string $field) {
    expect(fn () => app(SetUpProperty::class)->handle(wizardInput($overrides)))
        ->toThrow(fn (ValidationException $exception) => expect(array_keys($exception->errors()))->toBe([$field]));

    expect(Property::query()->count())->toBe(0)
        ->and(RoomType::query()->count())->toBe(0);
})->with([
    'room number used twice' => [['room_types' => [
        ['name' => 'Standar', 'rental_period' => 'monthly', 'price' => 900_000, 'room_numbers' => ['101']],
        ['name' => 'AC', 'rental_period' => 'monthly', 'price' => 1_400_000, 'room_numbers' => ['101']],
    ]], 'room_types.1.room_numbers'],
    'bank account without a number' => [['bank_accounts' => [
        ['kind' => 'bank', 'provider_name' => 'BCA', 'account_number' => '', 'account_holder' => 'Siti'],
    ]], 'bank_accounts.0.account_number'],
    'penalty amount missing' => [['penalty_amount' => null], 'penalty_amount'],
]);

it('lets only the owner run the wizard', function () {
    loginAs(staff(Role::Manager, $this->owner->tenant()->firstOrFail()));

    expect(fn () => app(SetUpProperty::class)->handle(wizardInput()))->toThrow(AuthorizationException::class);
});

it('tracks the onboarding steps from the data', function () {
    expect(OnboardingProgress::steps())->toBe([
        'property' => false, 'rooms' => false, 'bank_account' => false, 'contracts' => false, 'opening_balance' => false,
    ]);

    app(SetUpProperty::class)->handle(wizardInput());
    LeaseScenario::active(Room::query()->where('number', '101')->sole());

    expect(OnboardingProgress::steps())->toBe([
        'property' => true, 'rooms' => true, 'bank_account' => true, 'contracts' => true, 'opening_balance' => false,
    ])->and(OnboardingProgress::isComplete())->toBeFalse();
});
