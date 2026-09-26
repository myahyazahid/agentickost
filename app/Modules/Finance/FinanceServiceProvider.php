<?php

namespace App\Modules\Finance;

use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Finance\Listeners\ProvisionChartForNewTenant;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Tenancy\Events\TenantCreated;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;

class FinanceServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            PermissionRegistry::class,
            fn (PermissionRegistry $registry) => $registry->register(FinancePermission::class),
        );
    }

    public function boot(): void
    {
        parent::boot();

        Relation::enforceMorphMap([
            'account' => Account::class,
            'bank_account' => BankAccount::class,
        ]);

        Event::listen(TenantCreated::class, ProvisionChartForNewTenant::class);
    }
}
