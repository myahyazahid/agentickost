<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->beforeEach(function () {
        // Panels load their theme through Vite, and CI runs tests without building assets.
        $this->withoutVite();
        Http::preventStrayRequests();
        Sleep::fake(syncWithCarbon: true);
        Exceptions::fake();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function tenancy(): TenantContext
{
    return app(TenantContext::class);
}

function actors(): ActorContext
{
    return app(ActorContext::class);
}

/**
 * Create a staff member with the given role, in a new tenant unless one is given.
 */
function staff(Role $role, ?Tenant $tenant = null): User
{
    return User::factory()
        ->withRole($role)
        ->create($tenant !== null ? ['tenant_id' => $tenant->id] : []);
}

/**
 * Act as the user the way the `app` panel does: logged in, with the user's
 * tenant and the user as the actor.
 */
function loginAs(User $user): User
{
    test()->actingAs($user);

    tenancy()->set($user->tenant()->firstOrFail());
    actors()->set(Actor::user($user));

    return $user;
}
