<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A super admin session inside a tenant (FR-TNT-05). The impersonation flow
 * itself is built in M1.9; the table exists now so audit logs can reference it.
 */
#[Fillable(['platform_admin_id', 'tenant_id', 'reason', 'started_at', 'ended_at', 'ip_address'])]
class ImpersonationLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<PlatformAdmin, $this>
     */
    public function platformAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
