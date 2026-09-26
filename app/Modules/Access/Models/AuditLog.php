<?php

namespace App\Modules\Access\Models;

use App\Modules\Access\Database\Factories\AuditLogFactory;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Modules\Tenancy\Models\ImpersonationLog;
use App\Support\Actors\ActorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only: entries are never updated or deleted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property ActorType $actor_type
 * @property string|null $actor_id
 * @property string $event
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $reason
 * @property string|null $ip_address
 * @property string|null $impersonation_log_id
 */
#[Fillable([
    'tenant_id', 'actor_type', 'actor_id', 'event', 'subject_type', 'subject_id',
    'old_values', 'new_values', 'reason', 'ip_address', 'impersonation_log_id',
])]
#[UseFactory(AuditLogFactory::class)]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Audit log tidak dapat diubah.'));
        static::deleting(fn (): never => throw new LogicException('Audit log tidak dapat dihapus.'));
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<ImpersonationLog, $this>
     */
    public function impersonationLog(): BelongsTo
    {
        return $this->belongsTo(ImpersonationLog::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}
