<?php

namespace App\Modules\Access\Audit;

use App\Modules\Access\Models\AuditLog;
use App\Support\Actors\ActorContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes audit log entries (FR-USR-04). Model changes are recorded by the
 * Auditable trait; actions call record() for business events that need a
 * name or a reason, such as `penalty.waived`.
 */
final class AuditLogger
{
    public function __construct(private readonly ActorContext $actors) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        string $event,
        Model $subject,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
    ): AuditLog {
        $actor = $this->actors->current();

        return AuditLog::create([
            'tenant_id' => $subject->getAttribute('tenant_id'),
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => $actor->ipAddress,
            'impersonation_log_id' => $actor->impersonationLogId,
        ]);
    }
}
