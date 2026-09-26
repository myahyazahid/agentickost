<?php

namespace App\Modules\Tenancy\Concerns;

use App\Modules\Tenancy\Exceptions\TenantMismatch;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Scopes\TenantScope;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant data (NFR-ISO-01).
 *
 * - Every query is limited to the current tenant; without one it throws.
 * - tenant_id is filled from the current tenant on create.
 * - Writing a record of another tenant, or changing tenant_id, throws.
 *
 * Creating with an explicit tenant_id outside any tenant context is allowed,
 * for provisioning code, seeders, and factories.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', $context->id());

                return;
            }

            self::ensureSameTenant($model, $context);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw TenantMismatch::immutable($model);
            }

            self::ensureSameTenant($model, app(TenantContext::class));
        });

        static::deleting(function (Model $model): void {
            self::ensureSameTenant($model, app(TenantContext::class));
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    private static function ensureSameTenant(Model $model, TenantContext $context): void
    {
        $current = $context->currentId();

        if ($current !== null && $model->getAttribute('tenant_id') !== $current) {
            throw TenantMismatch::forModel($model, $current);
        }
    }
}
