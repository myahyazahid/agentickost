<?php

namespace App\Modules\Portal;

use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Portal\Enums\PortalPermission;
use App\Modules\Portal\Http\Middleware\AuthenticatePortal;
use App\Modules\Portal\Http\Middleware\UsePortalTenant;
use App\Modules\Portal\Models\Announcement;
use App\Modules\Portal\Models\OtpCode;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

/**
 * The resident portal (PRD §7.17): a mobile web app at /p/{tenant} where
 * residents and payers log in with a code sent to their phone.
 */
class PortalServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            PermissionRegistry::class,
            fn (PermissionRegistry $registry) => $registry->register(PortalPermission::class),
        );
    }

    public function boot(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('portal.tenant', UsePortalTenant::class);
        $router->aliasMiddleware('portal.auth', AuthenticatePortal::class);

        parent::boot();

        Relation::enforceMorphMap([
            'otp_code' => OtpCode::class,
            'announcement' => Announcement::class,
        ]);

        // Livewire requests from portal pages run through the same checks.
        Livewire::addPersistentMiddleware([UsePortalTenant::class, AuthenticatePortal::class]);

        Blade::anonymousComponentPath($this->modulePath('Resources/views/components'), 'portal');
    }
}
