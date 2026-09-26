<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Tenancy\Exceptions\MissingTenantContext;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Actors\Actor;
use Illuminate\Support\Facades\Context;
use Tests\Fixtures\RecordContextJob;

/**
 * Clear everything a fresh queue worker process would not have.
 */
function forgetRequestState(): void
{
    tenancy()->forget();
    actors()->forget();
    Context::flush();
    app()->forgetScopedInstances();
}

beforeEach(function () {
    config(['queue.default' => 'database']);

    RecordContextJob::$tenantId = null;
    RecordContextJob::$actorType = null;
    RecordContextJob::$actorId = null;
});

it('restores the previous tenant after run()', function () {
    [$outer, $inner] = Tenant::factory()->count(2)->create();

    tenancy()->set($outer);
    $seenInside = tenancy()->run($inner, fn () => tenancy()->id());

    expect($seenInside)->toBe($inner->id)
        ->and(tenancy()->id())->toBe($outer->id);
});

it('clears the tenant after run() when there was none before', function () {
    tenancy()->run(Tenant::factory()->create(), fn () => null);

    tenancy()->id();
})->throws(MissingTenantContext::class);

it('visits every active tenant and skips frozen ones', function () {
    [$first, $second] = Tenant::factory()->count(2)->create();
    $frozen = Tenant::factory()->frozen()->create();

    $visited = [];
    tenancy()->each(function (Tenant $tenant) use (&$visited) {
        $visited[] = tenancy()->id();
    });

    expect($visited)->toContain($first->id, $second->id)->not->toContain($frozen->id);
});

it('runs a queued job under the tenant and actor that dispatched it', function () {
    $owner = loginAs(staff(Role::Owner));

    RecordContextJob::dispatch();
    forgetRequestState();

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true]);

    expect(RecordContextJob::$tenantId)->toBe($owner->tenant_id)
        ->and(RecordContextJob::$actorType)->toBe('user')
        ->and(RecordContextJob::$actorId)->toBe($owner->id);
});

it('runs a job dispatched by a scheduled command as the system in that tenant', function () {
    $tenant = Tenant::factory()->create();

    // A statement body, not an arrow function: a returned PendingDispatch would
    // only dispatch after run() has already restored the previous context.
    actors()->actingAs(Actor::system(), fn () => tenancy()->run($tenant, function () {
        RecordContextJob::dispatch();
    }));
    forgetRequestState();

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true]);

    expect(RecordContextJob::$tenantId)->toBe($tenant->id)
        ->and(RecordContextJob::$actorType)->toBe('system');
});

it('runs a job dispatched without a tenant without one', function () {
    tenancy()->set(Tenant::factory()->create());
    forgetRequestState();

    RecordContextJob::dispatch();

    tenancy()->set(Tenant::factory()->create());
    forgetRequestState();

    $this->artisan('queue:work', ['connection' => 'database', '--once' => true]);

    expect(RecordContextJob::$tenantId)->toBeNull();
});
