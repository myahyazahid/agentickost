<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Property\Actions\CreateProperty;
use App\Modules\Property\Enums\GenderPolicy;
use App\Modules\Property\Models\Property;
use App\Support\Actors\ActorType;
use App\Support\Timezone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function propertyInput(array $overrides = []): array
{
    return [
        'name' => 'Kost Melati',
        'code' => 'MLT',
        'address' => 'Jl. Melati No. 5',
        'city' => 'Bandung',
        'province' => 'Jawa Barat',
        'timezone' => Timezone::Wib->value,
        'gender_policy' => GenderPolicy::Female->value,
        ...$overrides,
    ];
}

it('creates a property in the owner\'s tenant and audits it as the owner', function () {
    $owner = loginAs(staff(Role::Owner));

    $property = app(CreateProperty::class)->handle(propertyInput());

    $entry = AuditLog::query()->where('subject_id', $property->id)->sole();
    expect($property->tenant_id)->toBe($owner->tenant_id)
        ->and($property->gender_policy)->toBe(GenderPolicy::Female)
        ->and($entry->event)->toBe('property.created')
        ->and($entry->actor_type)->toBe(ActorType::User)
        ->and($entry->actor_id)->toBe($owner->id);
});

it('forbids roles without property.create', function (Role $role) {
    loginAs(staff($role));

    app(CreateProperty::class)->handle(propertyInput());
})->with([Role::Manager, Role::Caretaker, Role::Accountant])->throws(AuthorizationException::class);

it('rejects a code already used in the same tenant', function () {
    $owner = loginAs(staff(Role::Owner));
    Property::factory()->create(['code' => 'MLT']);

    app(CreateProperty::class)->handle(propertyInput(['code' => 'MLT']));
})->throws(ValidationException::class);

it('accepts a code that only another tenant uses', function () {
    $otherOwner = staff(Role::Owner);
    tenancy()->run($otherOwner->tenant()->firstOrFail(), fn () => Property::factory()->create(['code' => 'MLT']));
    loginAs(staff(Role::Owner));

    $property = app(CreateProperty::class)->handle(propertyInput(['code' => 'MLT']));

    expect($property->code)->toBe('MLT');
});

it('rejects a time zone outside Indonesia', function () {
    loginAs(staff(Role::Owner));

    app(CreateProperty::class)->handle(propertyInput(['timezone' => 'Asia/Singapore']));
})->throws(ValidationException::class);
