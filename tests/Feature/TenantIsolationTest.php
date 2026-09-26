<?php

/*
 * Tenant isolation harness (NFR-ISO-05). Models are discovered from
 * app/Modules/{Name}/Models, so a new model is covered without editing this file.
 * Every tenant model needs a factory that works inside a tenant context.
 */

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Tenancy\Exceptions\MissingTenantContext;
use App\Modules\Tenancy\Exceptions\TenantMismatch;
use App\Modules\Tenancy\Models\ImpersonationLog;
use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TenantModels;

dataset('tenant models', fn (): array => TenantModels::scoped());

/**
 * @param  class-string<Model>  $model
 * @return array{0: Tenant, 1: Model, 2: Tenant, 3: Model}
 */
function recordsInTwoTenants(string $model): array
{
    [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();

    return [
        $tenantA,
        tenancy()->run($tenantA, fn () => $model::factory()->create()),
        $tenantB,
        tenancy()->run($tenantB, fn () => $model::factory()->create()),
    ];
}

it('lists only records of the current tenant', function (string $model) {
    [$tenantA, $recordA, , $recordB] = recordsInTwoTenants($model);

    $ids = tenancy()->run($tenantA, fn () => $model::query()->pluck('id'));

    expect($ids)->toContain($recordA->getKey())->not->toContain($recordB->getKey());
})->with('tenant models');

it('does not find a record of another tenant by id', function (string $model) {
    [$tenantA, , , $recordB] = recordsInTwoTenants($model);

    expect(tenancy()->run($tenantA, fn () => $model::query()->find($recordB->getKey())))->toBeNull();
})->with('tenant models');

it('does not delete records of another tenant through a query', function (string $model) {
    [$tenantA, , $tenantB, $recordB] = recordsInTwoTenants($model);

    $deleted = tenancy()->run($tenantA, fn () => $model::query()->whereKey($recordB->getKey())->delete());

    expect($deleted)->toBe(0)
        ->and(tenancy()->run($tenantB, fn () => $model::query()->whereKey($recordB->getKey())->exists()))->toBeTrue();
})->with('tenant models');

it('fills tenant_id from the current tenant', function (string $model) {
    $tenant = Tenant::factory()->create();

    $record = tenancy()->run($tenant, fn () => $model::factory()->create());

    expect($record->getAttribute('tenant_id'))->toBe($tenant->id);
})->with('tenant models');

it('refuses to save a record for another tenant', function (string $model) {
    [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();

    $record = tenancy()->run($tenantA, fn () => $model::factory()->make());
    $record->setAttribute('tenant_id', $tenantB->id);

    tenancy()->run($tenantA, fn () => $record->save());
})->with('tenant models')->throws(TenantMismatch::class);

it('refuses to move a record to another tenant', function (string $model) {
    [$tenantA, $recordA, $tenantB] = recordsInTwoTenants($model);

    $recordA->setAttribute('tenant_id', $tenantB->id);

    tenancy()->run($tenantA, fn () => $recordA->save());
})->with('tenant models')->throws(TenantMismatch::class);

it('refuses to query without a tenant context', function (string $model) {
    $model::query()->get();
})->with('tenant models')->throws(MissingTenantContext::class);

test('every model is either tenant data or a known platform model', function () {
    $platformModels = [Tenant::class, PlatformAdmin::class, ImpersonationLog::class];

    $unscoped = array_diff(TenantModels::all(), TenantModels::scoped(), $platformModels);

    expect($unscoped)->toBe([]);
});

test('every tenant model is audited', function () {
    $notAudited = array_filter(
        TenantModels::scoped(),
        fn (string $model): bool => $model !== AuditLog::class
            && ! in_array(Auditable::class, class_uses_recursive($model), true),
    );

    expect($notAudited)->toBe([]);
});

test('every table with a tenant_id column belongs to a tenant model', function () {
    $tenantModelTables = array_map(fn (string $model): string => (new $model)->getTable(), TenantModels::scoped());

    // Scoped by spatie/laravel-permission teams, or platform data that references a tenant.
    $handledElsewhere = ['roles', 'model_has_roles', 'model_has_permissions', 'impersonation_logs'];

    $tablesWithTenantId = array_filter(
        Schema::getTableListing(schemaQualified: false),
        fn (string $table): bool => Schema::hasColumn($table, 'tenant_id'),
    );

    expect(array_values(array_diff($tablesWithTenantId, $tenantModelTables, $handledElsewhere)))->toBe([]);
});
