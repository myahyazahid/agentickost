<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Property\Models\Room;
use App\Modules\Tenancy\Actions\FreezeTenant;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Filament\Admin\Pages\PlatformSettingsPage;
use App\Modules\Tenancy\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use App\Modules\Tenancy\Filament\Admin\Resources\Tenants\Pages\ViewTenant;
use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\PlatformSettings;
use App\Modules\Tenancy\Support\TenantUsage;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorType;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->travelTo('2026-10-05 03:00:00');
    $this->admin = PlatformAdmin::factory()->create(['name' => 'Dimas Agentic Kost']);
    $this->owner = staff(Role::Owner);
    $this->tenant = $this->owner->tenant()->firstOrFail();
    $this->tenant->update(['trial_ends_at' => now()->addDays(10)]);
    tenancy()->run($this->tenant, fn () => Room::factory()->count(3)->create());

    $this->actingAs($this->admin, 'platform');
    actors()->set(Actor::platformAdmin($this->admin));
});

it('lists tenants with their status, owner, and usage', function () {
    $frozen = Tenant::factory()->frozen()->create();
    $record = TenantUsage::query()->whereKey($this->tenant->id)->sole();

    expect($record->getAttribute('owner_email'))->toBe($this->owner->email)
        ->and((int) $record->getAttribute('rooms_count'))->toBe(3)
        ->and((int) $record->getAttribute('staff_count'))->toBe(1);

    Livewire::test(ListTenants::class)
        ->assertCanSeeTableRecords([$this->tenant, $frozen])
        ->assertSee(['Trial', 'sisa 10 hari', 'Dibekukan', $this->owner->email])
        ->filterTable('status', TenantStatus::Frozen->value)
        ->assertCanSeeTableRecords([$frozen])
        ->assertCanNotSeeTableRecords([$this->tenant]);
});

it('freezes a tenant with a reason the owner can read, and opens it again', function () {
    Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->callAction('freeze', ['reason' => 'Permintaan owner, kost tutup sementara'])
        ->assertNotified('Tenant dibekukan');

    expect($this->tenant->refresh()->status())->toBe(TenantStatus::Frozen)
        ->and($this->tenant->frozen_reason)->toBe('Permintaan owner, kost tutup sementara');

    $entry = tenancy()->run($this->tenant, fn () => AuditLog::query()->where('event', 'tenant.frozen')->sole());
    expect($entry->actor_type)->toBe(ActorType::PlatformAdmin)
        ->and($entry->actor_id)->toBe($this->admin->id)
        ->and($entry->reason)->toBe('Permintaan owner, kost tutup sementara');

    $this->actingAs($this->owner, 'web')->get('/app')->assertForbidden();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin, 'platform');

    Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->assertActionHidden('freeze')
        ->callAction('unfreeze')
        ->assertNotified('Tenant aktif lagi');

    expect($this->tenant->refresh()->isFrozen())->toBeFalse();
    $this->actingAs($this->owner, 'web')->get('/app')->assertOk();
});

it('moves the end of a trial to the end of the chosen day in the tenant time zone', function () {
    Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->callAction('changeTrial', ['trial_ends_on' => '2026-11-30', 'reason' => 'Kost pilot'])
        ->assertNotified('Akhir trial diubah');

    expect($this->tenant->refresh()->trial_ends_at?->toIso8601String())->toBe('2026-11-30T16:59:59+00:00')
        ->and(tenancy()->run($this->tenant, fn () => AuditLog::query()->where('event', 'tenant.trial_changed')->exists()))->toBeTrue();
});

it('sets the trial length for new tenants', function () {
    Livewire::test(PlatformSettingsPage::class)
        ->assertSchemaStateSet(['trial_days' => 14])
        ->fillForm(['trial_days' => 21])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Pengaturan tersimpan');

    expect(PlatformSettings::trialDays())->toBe(21);
});

it('keeps tenant management away from tenant staff', function () {
    actors()->set(Actor::user($this->owner));

    expect(fn () => app(FreezeTenant::class)->handle($this->tenant, ['reason' => 'Coba bekukan']))
        ->toThrow(AuthorizationException::class);
});
