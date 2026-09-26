<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Tenancy\Models\Tenant;

it('creates a tenant with its owner', function () {
    $this->artisan('tenant:create', [
        'name' => 'Kost Melati',
        '--owner-name' => 'Siti',
        '--owner-email' => 'siti@example.com',
        '--owner-password' => 'password-rahasia',
    ])->assertSuccessful();

    $tenant = Tenant::query()->where('name', 'Kost Melati')->sole();

    tenancy()->run($tenant, function () use ($tenant) {
        $owner = User::query()->where('email', 'siti@example.com')->sole();

        expect($tenant->slug)->toBe('kost-melati')
            ->and($owner->hasRole(Role::Owner->value))->toBeTrue();
    });
});
