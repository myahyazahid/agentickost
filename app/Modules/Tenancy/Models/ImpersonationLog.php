<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A super admin session inside a tenant (FR-TNT-05), working as one of the
 * tenant's owner accounts. Audit log entries made during the session point
 * here, and the tenant's owner can read the list.
 *
 * @property string $id
 * @property string $platform_admin_id
 * @property string $tenant_id
 * @property string|null $impersonated_user_id
 * @property string $reason
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property string $ip_address
 */
#[Fillable(['platform_admin_id', 'tenant_id', 'impersonated_user_id', 'reason', 'started_at', 'ended_at', 'ip_address'])]
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

    public function isActive(): bool
    {
        return $this->ended_at === null;
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
