<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Exceptions\TenantMismatch;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * @return array{0: Tenant, 1: Property, 2: Property}
 */
function tenantWithTwoProperties(): array
{
    $tenant = Tenant::factory()->create();
    [$assigned, $other] = tenancy()->run($tenant, fn () => Property::factory()->count(2)->create()->all());

    return [$tenant, $assigned, $other];
}

it('lets owners and accountants see every property', function (Role $role) {
    [$tenant, $first, $second] = tenantWithTwoProperties();
    $user = loginAs(staff($role, $tenant));

    expect(Property::query()->accessibleBy($user)->pluck('id')->all())->toEqualCanonicalizing([$first->id, $second->id])
        ->and($user->can('view', $second))->toBeTrue();
})->with([Role::Owner, Role::Accountant]);

it('limits managers and caretakers to their assigned properties', function (Role $role) {
    [$tenant, $assigned, $other] = tenantWithTwoProperties();
    $owner = staff(Role::Owner, $tenant);
    $user = staff($role, $tenant);
    loginAs($owner);
    app(AssignStaffToProperty::class)->handle($assigned, $user);

    loginAs($user);

    expect(Property::query()->accessibleBy($user)->pluck('id')->all())->toBe([$assigned->id])
        ->and($user->can('view', $assigned))->toBeTrue()
        ->and($user->can('view', $other))->toBeFalse();
})->with([Role::Manager, Role::Caretaker]);

it('forbids a manager from assigning staff', function () {
    [$tenant, $property] = tenantWithTwoProperties();
    $caretaker = staff(Role::Caretaker, $tenant);
    loginAs(staff(Role::Manager, $tenant));

    app(AssignStaffToProperty::class)->handle($property, $caretaker);
})->throws(AuthorizationException::class);

it('refuses to assign staff of another tenant', function () {
    [$tenant, $property] = tenantWithTwoProperties();
    $outsider = staff(Role::Caretaker);
    loginAs(staff(Role::Owner, $tenant));

    app(AssignStaffToProperty::class)->handle($property, $outsider);
})->throws(TenantMismatch::class);
