<?php

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Events\TenantChangedByPlatform;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Opens a frozen tenant again (FR-TNT-06).
 */
final class UnfreezeTenant extends Action
{
    public function handle(Tenant $tenant): Tenant
    {
        $this->authorize('freeze', $tenant);

        return $this->transaction(function () use ($tenant): Tenant {
            $tenant = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            if (! $tenant->isFrozen()) {
                throw ValidationException::withMessages(['status' => "{$tenant->name} tidak sedang dibekukan."]);
            }

            $old = ['frozen_at' => $tenant->frozen_at?->toIso8601String(), 'frozen_reason' => $tenant->frozen_reason];

            $tenant->frozen_at = null;
            $tenant->frozen_reason = null;
            $tenant->save();

            TenantChangedByPlatform::dispatch($tenant, 'tenant.unfrozen', $old, ['frozen_at' => null]);

            return $tenant;
        });
    }
}
