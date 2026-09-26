<?php

use App\Modules\Tenancy\Exceptions\MissingTenantContext;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\TenantCache;

it('keeps cached values of one tenant away from another', function () {
    [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();

    tenancy()->run($tenantA, fn () => app(TenantCache::class)->put('occupancy', 42));

    expect(tenancy()->run($tenantA, fn () => app(TenantCache::class)->get('occupancy')))->toBe(42)
        ->and(tenancy()->run($tenantB, fn () => app(TenantCache::class)->get('occupancy')))->toBeNull();
});

it('prefixes keys with the tenant id', function () {
    $tenant = Tenant::factory()->create();

    $key = tenancy()->run($tenant, fn () => app(TenantCache::class)->key('occupancy'));

    expect($key)->toBe("tenant:{$tenant->id}:occupancy");
});

it('refuses to cache without a tenant context', function () {
    app(TenantCache::class)->put('occupancy', 42);
})->throws(MissingTenantContext::class);
