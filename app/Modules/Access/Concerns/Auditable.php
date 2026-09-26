<?php

namespace App\Modules\Access\Concerns;

use App\Modules\Access\Audit\AuditLogger;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Records created, updated, deleted, and restored events of the model in the
 * audit log, in the same transaction as the change.
 *
 * Events are named `{morph alias}.{action}`, for example `property.updated`.
 * Hidden attributes are stored as "[redacted]".
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            $model->writeAudit('created', null, $model->auditableValues($model->getAttributes()));
        });

        static::updated(function (self $model): void {
            $changes = $model->auditableValues($model->getChanges());

            if ($changes === []) {
                return;
            }

            $original = $model->auditableValues(array_intersect_key($model->getRawOriginal(), $changes));

            $model->writeAudit('updated', $original, $changes);
        });

        static::deleted(function (self $model): void {
            $model->writeAudit('deleted', $model->auditableValues($model->getAttributes()), null);
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::registerModelEvent('restored', function (self $model): void {
                $model->writeAudit('restored', null, null);
            });
        }
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    protected function writeAudit(string $action, ?array $oldValues, ?array $newValues): void
    {
        app(AuditLogger::class)->record($this->getMorphClass().'.'.$action, $this, $oldValues, $newValues);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function auditableValues(array $values): array
    {
        $excluded = ['created_at', 'updated_at', 'deleted_at', 'remember_token'];
        $result = [];

        foreach ($values as $key => $value) {
            if (in_array($key, $excluded, true)) {
                continue;
            }

            $result[$key] = in_array($key, $this->getHidden(), true) ? '[redacted]' : $value;
        }

        return $result;
    }
}
