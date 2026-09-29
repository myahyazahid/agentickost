<?php

use App\Modules\Access\Actions\StartImpersonation;
use App\Modules\Access\Enums\Role;
use App\Modules\Access\Filament\App\Pages\SupportSessions;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Access\Support\Impersonation;
use App\Modules\Tenancy\Filament\Admin\Resources\Tenants\Pages\ViewTenant;
use App\Modules\Tenancy\Filament\Admin\Resources\Tenants\TenantResource;
use App\Modules\Tenancy\Models\ImpersonationLog;
use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorType;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-05 03:00:00');
    $this->admin = PlatformAdmin::factory()->create(['name' => 'Dimas KostPilot']);
    $this->owner = staff(Role::Owner);
    $this->tenant = $this->owner->tenant()->firstOrFail();
    $this->actingAs($this->admin, 'platform');
});

function startSession(string $reason = 'Owner minta dicek tagihan Oktober'): ImpersonationLog
{
    return actors()->actingAs(Actor::platformAdmin(test()->admin), function () use ($reason): ImpersonationLog {
        [$log, $owner] = app(StartImpersonation::class)->handle(test()->tenant, ['reason' => $reason]);
        app(Impersonation::class)->enter($log, $owner);

        return $log;
    });
}

it('enters a tenant from the admin panel with a written reason', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actors()->set(Actor::platformAdmin($this->admin));

    Livewire::test(ViewTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->callAction('impersonate', ['reason' => 'Owner minta dicek tagihan Oktober'])
        ->assertRedirect('/app');

    $log = ImpersonationLog::query()->sole();
    expect($log->platform_admin_id)->toBe($this->admin->id)
        ->and($log->impersonated_user_id)->toBe($this->owner->id)
        ->and($log->reason)->toBe('Owner minta dicek tagihan Oktober');

    $this->assertAuthenticatedAs($this->owner, 'web');
    $this->assertAuthenticatedAs($this->admin, 'platform');
});

it('shows the banner in the tenant panel and records work under the super admin', function () {
    $log = startSession();

    $this->get('/app')->assertOk()->assertSee(['sebagai super admin Dimas KostPilot', 'Keluar ke panel admin']);

    $this->post(route('access.impersonation.end'))
        ->assertRedirect(TenantResource::getUrl('view', ['record' => $this->tenant], panel: 'admin'));

    expect($log->refresh()->ended_at)->not->toBeNull();
    $this->assertGuest('web');
    $this->assertAuthenticatedAs($this->admin, 'platform');

    $entries = tenancy()->run($this->tenant, fn () => AuditLog::query()->whereIn('event', ['impersonation.started', 'impersonation.ended'])->orderBy('id')->get());
    expect($entries->pluck('event')->all())->toBe(['impersonation.started', 'impersonation.ended'])
        ->and($entries->pluck('actor_type')->unique()->all())->toBe([ActorType::PlatformAdmin])
        ->and($entries->last()?->impersonation_log_id)->toBe($log->id);
});

it('drops the owner login when the admin leaves the admin panel', function () {
    startSession();

    auth('platform')->logout();

    $this->get('/app')->assertRedirect('/admin');
    $this->assertGuest('web');
});

it('lists every session for the owner', function () {
    startSession('Membantu impor data kamar');
    app(Impersonation::class)->leave();

    Filament::setCurrentPanel(Filament::getPanel('app'));
    auth()->shouldUse('web');
    loginAs($this->owner);

    Livewire::test(SupportSessions::class)
        ->assertSee(['Dimas KostPilot', 'Membantu impor data kamar', 'Masih berjalan']);
});

it('refuses a session without a real reason or into a frozen tenant', function () {
    expect(fn () => startSession('cek'))->toThrow(ValidationException::class);

    $this->tenant->forceFill(['frozen_at' => now()])->save();
    expect(fn () => startSession())->toThrow(ValidationException::class, 'sedang dibekukan');
});
