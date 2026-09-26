<?php

namespace App\Modules\Access\Listeners;

use App\Modules\Access\Actions\SyncTenantRoles;
use App\Modules\Tenancy\Events\TenantCreated;
use App\Modules\Tenancy\TenantContext;

final class ProvisionTenantRoles
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly SyncTenantRoles $syncTenantRoles,
    ) {}

    public function handle(TenantCreated $event): void
    {
        $this->tenants->run($event->tenant, fn () => $this->syncTenantRoles->handle());
    }
}
