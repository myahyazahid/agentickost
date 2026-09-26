<?php

namespace App\Modules\Lease;

use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Lease\Console\ActivateDueRenewals;
use App\Modules\Lease\Console\RemindEndingContracts;
use App\Modules\Lease\Enums\LeasePermission;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractHold;
use App\Modules\Lease\Models\ContractResident;
use App\Modules\Lease\Models\Payer;
use App\Modules\Lease\Models\Resident;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class LeaseServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            PermissionRegistry::class,
            fn (PermissionRegistry $registry) => $registry->register(LeasePermission::class),
        );
    }

    public function boot(): void
    {
        parent::boot();

        Relation::enforceMorphMap([
            'resident' => Resident::class,
            'payer' => Payer::class,
            'contract' => Contract::class,
            'contract_resident' => ContractResident::class,
            'contract_hold' => ContractHold::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([ActivateDueRenewals::class, RemindEndingContracts::class]);
        }
    }
}
