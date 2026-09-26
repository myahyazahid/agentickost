<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\ActorContext;
use Illuminate\Support\Facades\Route;

it('sets the tenant and actor from the logged-in user', function () {
    Route::middleware(['web', 'auth', 'tenant'])->get('/_test/context', fn (TenantContext $tenants, ActorContext $actors) => [
        'tenant_id' => $tenants->id(),
        'actor_type' => $actors->current()->type->value,
        'actor_id' => $actors->current()->id,
        'ip_address' => $actors->current()->ipAddress,
    ]);
    $owner = staff(Role::Owner);

    $this->actingAs($owner)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->getJson('/_test/context')
        ->assertOk()
        ->assertExactJson([
            'tenant_id' => $owner->tenant_id,
            'actor_type' => 'user',
            'actor_id' => $owner->id,
            'ip_address' => '198.51.100.20',
        ]);
});
