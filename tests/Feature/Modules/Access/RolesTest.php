<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Tenancy\Models\Tenant;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * @return list<string>
 */
function permissionsOfRole(Tenant $tenant, Role $role): array
{
    return tenancy()->run($tenant, fn () => RoleModel::findByName($role->value)
        ->permissions->pluck('name')->sort()->values()->all());
}

/**
 * @return list<string>
 */
function registeredPermissionsOf(Role $role): array
{
    $names = app(PermissionRegistry::class)->namesFor($role);
    sort($names);

    return $names;
}

it('provisions every staff role with its registered default permissions', function (Role $role) {
    $tenant = Tenant::factory()->create();

    expect(permissionsOfRole($tenant, $role))->toBe(registeredPermissionsOf($role))->not->toBeEmpty();
})->with(Role::staff());

it('does not carry a role into another tenant', function () {
    $owner = staff(Role::Owner);
    $otherTenant = Tenant::factory()->create();

    $hasRoleIn = fn (Tenant $tenant) => tenancy()->run($tenant, fn () => $owner->fresh()?->hasRole(Role::Owner->value));

    expect($hasRoleIn($owner->tenant()->firstOrFail()))->toBeTrue()
        ->and($hasRoleIn($otherTenant))->toBeFalse();
});

it('restores missing role permissions with access:sync-roles', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->run($tenant, fn () => RoleModel::findByName(Role::Manager->value)->syncPermissions([]));

    $this->artisan('access:sync-roles')->assertSuccessful();

    expect(permissionsOfRole($tenant, Role::Manager))->toBe(registeredPermissionsOf(Role::Manager));
});
