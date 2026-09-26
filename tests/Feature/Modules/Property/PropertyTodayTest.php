<?php

use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Timezone;
use Carbon\CarbonImmutable;

it('turns the day over at midnight in the property time zone', function (Timezone $zone, string $utcNow, string $expected) {
    tenancy()->set(Tenant::factory()->create());
    $property = Property::factory()->create(['timezone' => $zone]);
    $this->travelTo(CarbonImmutable::parse($utcNow, 'UTC'));

    expect($property->today()->toDateString())->toBe($expected);
})->with([
    'WIB just before midnight' => [Timezone::Wib, '2026-12-19 16:59:59', '2026-12-19'],
    'WIB just after midnight' => [Timezone::Wib, '2026-12-19 17:00:00', '2026-12-20'],
    'WITA just after midnight' => [Timezone::Wita, '2026-12-19 16:00:00', '2026-12-20'],
    'WIT just after midnight' => [Timezone::Wit, '2026-12-19 15:00:00', '2026-12-20'],
]);

it('compares with date attributes by calendar day', function () {
    tenancy()->set(Tenant::factory()->create());
    $property = Property::factory()->create(['timezone' => Timezone::Wib]);
    $this->travelTo(CarbonImmutable::parse('2026-12-20 01:00:00', 'UTC'));

    $startDate = CarbonImmutable::parse('2026-12-20');

    expect($startDate->lessThanOrEqualTo($property->today()))->toBeTrue()
        ->and($startDate->equalTo($property->today()))->toBeTrue();
});
