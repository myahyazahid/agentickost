<?php

namespace App\Modules\Tenancy\Events;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A super admin changed a tenant, for example froze it (FR-TNT-06). The
 * Access module writes it into the tenant's own audit log so the owner
 * can see it.
 */
final class TenantChangedByPlatform
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $event,
        public readonly array $oldValues = [],
        public readonly array $newValues = [],
        public readonly ?string $reason = null,
    ) {}
}
