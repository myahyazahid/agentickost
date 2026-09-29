<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Support\Filament\InitialsAvatarProvider;
use App\Support\Filament\PanelTheme;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
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

it('gives both panels the Agentic Kost theme', function (string $panel) {
    $panel = Filament::getPanel($panel);

    expect($panel->getViteTheme())->toBe(PanelTheme::STYLESHEET)
        ->and($panel->getFontFamily())->toBe(PanelTheme::FONT)
        ->and($panel->hasDarkMode())->toBeFalse()
        ->and($panel->getDefaultAvatarProvider())->toBe(InitialsAvatarProvider::class);
})->with(['admin', 'app']);

it('draws initials avatars in the theme red', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $user = User::factory()->make(['name' => 'Rina Kartika']);

    expect(app(InitialsAvatarProvider::class)->get($user))
        ->toStartWith('https://ui-avatars.com/api/?name=R+K&format=svg')
        ->toEndWith('&color=C42127&background=FCE4E4');
});

it('keeps panel text readable on the theme palette', function () {
    ['primary' => $primary, 'gray' => $gray] = Filament::getPanel('app')->getColors();

    // Solid buttons use primary 600 with a 500 hover; both must carry white text.
    expect(Color::calculateContrastRatio($primary[600], '#ffffff'))->toBeGreaterThanOrEqual(Color::WCAG_AA_TEXT)
        ->and(Color::calculateContrastRatio($primary[500], '#ffffff'))->toBeGreaterThanOrEqual(Color::WCAG_AA_TEXT)
        // Secondary text is gray 500 on white cards and on the gray 50 canvas.
        ->and(Color::calculateContrastRatio($gray[500], '#ffffff'))->toBeGreaterThanOrEqual(Color::WCAG_AA_TEXT)
        ->and(Color::calculateContrastRatio($gray[500], $gray[50]))->toBeGreaterThanOrEqual(Color::WCAG_AA_TEXT)
        // The active navigation item is primary 700 on a primary 50 tint.
        ->and(Color::calculateContrastRatio($primary[700], $primary[50]))->toBeGreaterThanOrEqual(Color::WCAG_AA_TEXT);
});
