<?php

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Events\TenantChangedByPlatform;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Closes a tenant's access (FR-TNT-06, PRD §9.6): its staff can no longer
 * log in and scheduled billing skips it, but its data stays.
 */
final class FreezeTenant extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Tenant $tenant, array $input): Tenant
    {
        $this->authorize('freeze', $tenant);

        $data = $this->validate($input, [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        return $this->transaction(function () use ($tenant, $data): Tenant {
            $tenant = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            if ($tenant->isFrozen()) {
                throw ValidationException::withMessages(['reason' => "{$tenant->name} sudah dibekukan."]);
            }

            $tenant->frozen_at = now();
            $tenant->frozen_reason = $data['reason'];
            $tenant->save();

            TenantChangedByPlatform::dispatch($tenant, 'tenant.frozen', ['frozen_at' => null], ['frozen_at' => $tenant->frozen_at->toIso8601String()], $data['reason']);

            return $tenant;
        });
    }
}
