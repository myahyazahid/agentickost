<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Tenancy\Actions\UpdateTenantProfile;
use App\Modules\Tenancy\Filament\App\Pages\BusinessProfile;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\TenantBranding;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake();
    $this->owner = loginAs(staff(Role::Owner));
    $this->tenant = tenancy()->tenant();
});

it('lets the owner set the business name, logo, and accent colour', function () {
    Livewire::test(BusinessProfile::class)
        ->fillForm([
            'name' => 'Kost Melati Group',
            'logo_path' => UploadedFile::fake()->image('logo.png', 300, 100),
            'brand_color' => '#B91C1C',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Profil usaha tersimpan');

    $tenant = $this->tenant->refresh();

    expect($tenant->name)->toBe('Kost Melati Group')
        ->and($tenant->brand_color)->toBe('#b91c1c')
        ->and($tenant->logo_path)->toStartWith("tenants/{$tenant->id}/branding/")
        ->and(TenantBranding::logoDataUri($tenant))->toStartWith('data:image/png;base64,');
});

it('deletes the old logo when it is replaced', function () {
    $first = UploadedFile::fake()->image('a.png')->store("tenants/{$this->tenant->id}/branding");
    $second = UploadedFile::fake()->image('b.png')->store("tenants/{$this->tenant->id}/branding");

    app(UpdateTenantProfile::class)->handle(['name' => 'Kost A', 'logo_path' => $first]);
    app(UpdateTenantProfile::class)->handle(['name' => 'Kost A', 'logo_path' => $second]);

    Storage::assertMissing($first);
    Storage::assertExists($second);
});

it('refuses a logo path outside the tenant logo folder', function () {
    $other = Tenant::factory()->create();
    $foreign = UploadedFile::fake()->image('c.png')->store("tenants/{$other->id}/branding");

    app(UpdateTenantProfile::class)->handle(['name' => 'Kost A', 'logo_path' => $foreign]);
})->throws(ValidationException::class, 'Logo harus gambar PNG atau JPG');

it('refuses a colour that is not a hex code', function () {
    app(UpdateTenantProfile::class)->handle(['name' => 'Kost A', 'brand_color' => 'red']);
})->throws(ValidationException::class);

it('keeps the profile to the owner', function () {
    loginAs(staff(Role::Manager, $this->tenant));

    $this->get('/app/profil-usaha')->assertForbidden();

    app(UpdateTenantProfile::class)->handle(['name' => 'Kost Lain']);
})->throws(AuthorizationException::class);
