<?php

namespace App\Modules\Access;

use App\Modules\Access\Console\CreateTenantCommand;
use App\Modules\Access\Console\SyncRolesCommand;
use App\Modules\Access\Enums\AccessPermission;
use App\Modules\Access\Filament\App\ImpersonationBanner;
use App\Modules\Access\Http\Middleware\SetTenantContext;
use App\Modules\Access\Listeners\ProvisionTenantRoles;
use App\Modules\Access\Listeners\RecordPlatformChange;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Access\Models\StaffInvitation;
use App\Modules\Access\Models\User;
use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Tenancy\Events\TenantChangedByPlatform;
use App\Modules\Tenancy\Events\TenantCreated;
use App\Modules\Tenancy\Scopes\TenantScope;
use App\Support\Modules\ModuleServiceProvider;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

class AccessServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            PermissionRegistry::class,
            fn (PermissionRegistry $registry) => $registry->register(AccessPermission::class),
        );
    }

    public function boot(): void
    {
        parent::boot();

        Relation::enforceMorphMap([
            'user' => User::class,
            'audit_log' => AuditLog::class,
            'staff_invitation' => StaffInvitation::class,
        ]);

        // Login and session lookups happen before a tenant is known, so the
        // user provider skips the tenant scope. All other User queries keep it.
        Auth::provider('tenant_users', function (Application $app, array $config): EloquentUserProvider {
            return (new EloquentUserProvider($app->make(Hasher::class), $config['model']))
                ->withQuery(fn (Builder $query) => $query->withoutGlobalScope(TenantScope::class));
        });

        Event::listen(TenantCreated::class, ProvisionTenantRoles::class);
        Event::listen(TenantChangedByPlatform::class, RecordPlatformChange::class);

        FilamentView::registerRenderHook(PanelsRenderHook::BODY_START, fn (): ?Htmlable => $this->app->make(ImpersonationBanner::class)->render());

        $this->app->make(Router::class)->aliasMiddleware('tenant', SetTenantContext::class);

        if ($this->app->runningInConsole()) {
            $this->commands([CreateTenantCommand::class, SyncRolesCommand::class]);
        }
    }
}
