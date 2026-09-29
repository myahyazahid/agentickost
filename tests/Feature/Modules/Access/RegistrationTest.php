<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Filament\App\Auth\Register;
use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Models\Account;
use App\Modules\Tenancy\Actions\UpdatePlatformSettings;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Scopes\TenantScope;
use App\Support\Actors\Actor;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Notification::fake();
    $this->travelTo('2026-10-05 03:00:00');
});

/**
 * @param  array<string, mixed>  $overrides
 */
function registerKost(array $overrides = []): Testable
{
    return Livewire::test(Register::class)
        ->fillForm([
            'business_name' => 'Kost Melati',
            'name' => 'Siti Aminah',
            'email' => 'Siti@Example.com',
            'phone' => '0812 3456 7890',
            'password' => 'rahasia-kost-123',
            'passwordConfirmation' => 'rahasia-kost-123',
            ...$overrides,
        ])
        ->call('register');
}

function registeredOwner(): User
{
    return User::query()->withoutGlobalScope(TenantScope::class)->where('email', 'siti@example.com')->sole();
}

it('creates a tenant on trial with an unverified owner and sends one verification email', function () {
    registerKost()->assertHasNoFormErrors();

    $owner = registeredOwner();
    $tenant = $owner->tenant()->firstOrFail();

    expect($tenant->name)->toBe('Kost Melati')
        ->and($tenant->status())->toBe(TenantStatus::Trial)
        ->and($tenant->trial_ends_at?->toDateString())->toBe('2026-10-19')
        ->and($owner->phone)->toBe('+6281234567890')
        ->and($owner->hasVerifiedEmail())->toBeFalse()
        ->and(tenancy()->run($tenant, fn () => $owner->hasRole(Role::Owner->value)))->toBeTrue()
        ->and(tenancy()->run($tenant, fn () => Account::system(AccountSubtype::Cash)->exists))->toBeTrue();

    $this->assertAuthenticatedAs($owner);
    Notification::assertSentToTimes($owner, VerifyEmail::class, 1);
});

it('keeps the panel closed until the email is verified, then opens the dashboard', function () {
    registerKost();
    $owner = registeredOwner();

    $this->get('/app')->assertRedirect(Filament::getPanel('app')->getEmailVerificationPromptUrl());

    $this->get(Filament::getPanel('app')->getVerifyEmailUrl($owner))->assertRedirect();

    expect($owner->refresh()->hasVerifiedEmail())->toBeTrue();
    $this->get('/app')->assertOk()->assertSee(['Persiapan KostPilot', 'Masa trial: sisa 14 hari']);
});

it('gives new tenants the trial length the super admin set', function () {
    actors()->actingAs(Actor::platformAdmin(PlatformAdmin::factory()->create()), fn () => app(UpdatePlatformSettings::class)->handle(['trial_days' => 30]));

    registerKost()->assertHasNoFormErrors()->assertSee('Coba gratis selama 30 hari.');

    expect(registeredOwner()->tenant()->firstOrFail()->trial_ends_at?->toDateString())->toBe('2026-11-04');
});

it('refuses a taken email and a phone number that is not one', function () {
    staff(Role::Owner)->update(['email' => 'siti@example.com']);

    registerKost(['email' => 'siti@example.com'])->assertHasFormErrors(['email']);
    registerKost(['email' => 'rina@example.com', 'phone' => '12'])->assertHasFormErrors(['phone']);

    expect(Tenant::query()->where('name', 'Kost Melati')->exists())->toBeFalse();
});

it('offers registration from the login page', function () {
    $this->get('/app/login')->assertOk()->assertSee('/app/register');
    $this->get('/app/register')->assertOk()->assertSee('Daftarkan kost Anda');
});
