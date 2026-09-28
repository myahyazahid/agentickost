<?php

namespace App\Modules\Maintenance;

use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Maintenance\Enums\MaintenancePermission;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\Models\TicketUpdate;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class MaintenanceServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            PermissionRegistry::class,
            fn (PermissionRegistry $registry) => $registry->register(MaintenancePermission::class),
        );
    }

    public function boot(): void
    {
        parent::boot();

        Relation::enforceMorphMap([
            'ticket' => Ticket::class,
            'ticket_update' => TicketUpdate::class,
        ]);
    }
}
