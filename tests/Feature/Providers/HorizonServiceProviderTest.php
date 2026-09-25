<?php

use App\Models\User;

it('forbids guests from the dashboard', function () {
    $this->get('/horizon')->assertForbidden();
});

it('forbids users whose email is not allowlisted', function () {
    config()->set('horizon.allowed_emails', ['ops@example.com']);

    $this->actingAs(User::factory()->create(['email' => 'owner@example.com']))
        ->get('/horizon')
        ->assertForbidden();
});

it('opens the dashboard for allowlisted users', function () {
    config()->set('horizon.allowed_emails', ['ops@example.com']);

    $this->actingAs(User::factory()->create(['email' => 'ops@example.com']))
        ->get('/horizon')
        ->assertOk();
});
