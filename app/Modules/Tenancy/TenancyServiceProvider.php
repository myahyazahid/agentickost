<?php

namespace App\Modules\Tenancy;

use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Tenancy\Enums\TenancyPermission;
use App\Modules\Tenancy\Models\ImpersonationLog;
use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Log\Context\Repository;
use Illuminate\Support\Facades\Context;

class TenancyServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            PermissionRegistry::class,
            fn (PermissionRegistry $registry) => $registry->register(TenancyPermission::class),
        );
    }

    public function boot(): void
    {
        parent::boot();

        Relation::enforceMorphMap([
            'tenant' => Tenant::class,
            'platform_admin' => PlatformAdmin::class,
            'impersonation_log' => ImpersonationLog::class,
        ]);

        Context::hydrated(function (Repository $context): void {
            $tenants = $this->app->make(TenantContext::class);
            $tenantId = $context->getHidden(TenantContext::CONTEXT_KEY);

            if (is_string($tenantId)) {
                $tenants->set(Tenant::query()->findOrFail($tenantId));
            } else {
                $tenants->forget();
            }
        });
    }
}
