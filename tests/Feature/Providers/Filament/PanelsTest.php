<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Tenancy\Models\PlatformAdmin;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('renders the login page', function (string $panel) {
    $this->get("/{$panel}/login")->assertOk();
})->with(['admin', 'app']);

it('redirects guests to the panel login page', function (string $panel) {
    $this->get("/{$panel}")->assertRedirect("/{$panel}/login");
})->with(['admin', 'app']);

it('logs staff in with email and password before any tenant is known', function () {
    $user = staff(Role::Caretaker);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('logs super admins in on the platform guard', function () {
    $admin = PlatformAdmin::factory()->create();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($admin, 'platform');
    $this->assertGuest('web');
});

it('opens the app panel for active tenant staff', function () {
    $this->actingAs(staff(Role::Caretaker))->get('/app')->assertOk();
});

it('refuses the app panel to deactivated staff', function () {
    $this->actingAs(User::factory()->inactive()->create())->get('/app')->assertForbidden();
});

it('keeps tenant staff out of the admin panel', function () {
    $this->actingAs(staff(Role::Owner))->get('/admin')->assertRedirect('/admin/login');
});

it('opens the admin panel for super admins', function () {
    $this->actingAs(PlatformAdmin::factory()->create(), 'platform')->get('/admin')->assertOk();
});

it('keeps super admins out of the app panel', function () {
    $this->actingAs(PlatformAdmin::factory()->create(), 'platform')->get('/app')->assertRedirect('/app/login');
});
