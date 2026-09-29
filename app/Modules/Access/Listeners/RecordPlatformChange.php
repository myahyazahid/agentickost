<?php

namespace App\Modules\Access\Listeners;

use App\Modules\Access\Audit\AuditLogger;
use App\Modules\Tenancy\Events\TenantChangedByPlatform;
use App\Modules\Tenancy\TenantContext;

/**
 * Writes a super admin's change to a tenant, such as freezing it, into that
 * tenant's audit log, where the owner can read it (FR-TNT-06).
 */
final class RecordPlatformChange
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(TenantChangedByPlatform $event): void
    {
        $this->tenants->run($event->tenant, fn () => $this->audit->record(
            $event->event,
            $event->tenant,
            $event->oldValues,
            $event->newValues,
            $event->reason,
        ));
    }
}
