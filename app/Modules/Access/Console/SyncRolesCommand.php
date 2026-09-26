<?php

namespace App\Modules\Access\Console;

use App\Modules\Access\Actions\SyncTenantRoles;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use Illuminate\Console\Command;

final class SyncRolesCommand extends Command
{
    protected $signature = 'access:sync-roles';

    protected $description = 'Sinkronkan permission dan peran bawaan untuk semua tenant aktif';

    public function handle(ActorContext $actors, TenantContext $tenants, SyncTenantRoles $syncTenantRoles): int
    {
        $count = 0;

        $actors->actingAs(Actor::system(), function () use ($tenants, $syncTenantRoles, &$count): void {
            $tenants->each(function (Tenant $tenant) use ($syncTenantRoles, &$count): void {
                $syncTenantRoles->handle();
                $count++;
            });
        });

        $this->components->info("Peran disinkronkan untuk {$count} tenant.");

        return self::SUCCESS;
    }
}
