<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Actions\ProvisionChartOfAccounts;
use App\Modules\Tenancy\Events\TenantCreated;
use App\Modules\Tenancy\TenantContext;

final class ProvisionChartForNewTenant
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly ProvisionChartOfAccounts $provision,
    ) {}

    public function handle(TenantCreated $event): void
    {
        $this->tenants->run($event->tenant, fn () => $this->provision->handle());
    }
}
