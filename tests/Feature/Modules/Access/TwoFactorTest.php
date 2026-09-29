<?php

use App\Modules\Access\Actions\StartImpersonation;
use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Access\Models\User;
use App\Modules\Access\Support\Impersonation;
use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Support\Actors\Actor;
use App\Support\Filament\Auth\Login;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    config(['agentickost.two_factor_required' => true]);
});

function enableAuthenticatorApp(PlatformAdmin|User $user): void
{
    $user->saveAppAuthenticationSecret(AppAuthentication::make()->generateSecret());
}

it('sends owners and accountants to set up an authenticator app', function (Role $role) {
    $user = staff($role);

    $this->actingAs($user)->get('/app')->assertRedirect(Filament::getPanel('app')->getSetUpRequiredMultiFactorAuthenticationUrl());
})->with([Role::Owner, Role::Accountant]);

it('lets other staff in without one', function (Role $role) {
    $this->actingAs(staff($role))->get('/app')->assertOk();
})->with([Role::Manager, Role::Caretaker]);

it('lets an owner in once the authenticator app is set up', function () {
    $owner = staff(Role::Owner);
    enableAuthenticatorApp($owner);

    $this->actingAs($owner)->get('/app')->assertOk();
    expect($owner->refresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('requires super admins to set one up', function () {
    $admin = PlatformAdmin::factory()->create();

    $this->actingAs($admin, 'platform')->get('/admin')->assertRedirect(Filament::getPanel('admin')->getSetUpRequiredMultiFactorAuthenticationUrl());

    enableAuthenticatorApp($admin);
    $this->actingAs($admin, 'platform')->get('/admin')->assertOk();
});

it('asks for the code at login once it is set up', function () {
    $owner = staff(Role::Owner);
    enableAuthenticatorApp($owner);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    Livewire::test(Login::class)
        ->fillForm(['email' => $owner->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertGuest();
});

it('keeps the secret and recovery codes out of the audit log', function () {
    $owner = loginAs(staff(Role::Owner));
    enableAuthenticatorApp($owner);
    $owner->saveAppAuthenticationRecoveryCodes(['aaaa-bbbb', 'cccc-dddd']);

    $values = AuditLog::query()->where('subject_id', $owner->id)->pluck('new_values')->toJson();

    expect($values)->not->toContain('aaaa-bbbb')
        ->and($values)->toContain('[redacted]')
        ->and($owner->refresh()->getAppAuthenticationRecoveryCodes())->toBe(['aaaa-bbbb', 'cccc-dddd']);
});

it('locks an account after too many failed logins from any address', function () {
    $user = staff(Role::Caretaker);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $key = 'login-account:app:'.hash('sha256', $user->email);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'salah'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(RateLimiter::attempts($key))->toBe(1);

    for ($i = 1; $i < Login::ATTEMPTS_PER_ACCOUNT; $i++) {
        RateLimiter::hit($key, Login::LOCKOUT_SECONDS);
    }

    Livewire::test(Login::class)
        ->fillForm(['email' => mb_strtoupper($user->email), 'password' => 'password'])
        ->call('authenticate')
        ->assertNotified('Terlalu banyak percobaan masuk');

    $this->assertGuest();
});

it('clears the account counter after a successful login', function () {
    $user = staff(Role::Caretaker);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $key = 'login-account:app:'.hash('sha256', $user->email);
    RateLimiter::hit($key, Login::LOCKOUT_SECONDS);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
    expect(RateLimiter::attempts($key))->toBe(0);
});

it('does not ask a super admin inside a tenant to set up the owner account', function () {
    $admin = PlatformAdmin::factory()->create();
    enableAuthenticatorApp($admin);
    $owner = staff(Role::Owner);
    $this->actingAs($admin, 'platform');

    actors()->actingAs(Actor::platformAdmin($admin), function () use ($owner): void {
        [$log, $account] = app(StartImpersonation::class)->handle($owner->tenant()->firstOrFail(), ['reason' => 'Owner minta dicek tagihan']);
        app(Impersonation::class)->enter($log, $account);
    });

    $this->get('/app')->assertOk();
});
