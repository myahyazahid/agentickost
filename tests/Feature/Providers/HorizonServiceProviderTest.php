<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Tenancy\Models\PlatformAdmin;

it('forbids guests from the dashboard', function () {
    $this->get('/horizon')->assertForbidden();
});

it('forbids tenant staff from the dashboard', function () {
    $this->actingAs(staff(Role::Owner))->get('/horizon')->assertForbidden();
});

it('opens the dashboard for super admins', function () {
    $this->actingAs(PlatformAdmin::factory()->create(), 'platform')->get('/horizon')->assertOk();
});
