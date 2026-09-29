<?php

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Events\TenantChangedByPlatform;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Actions\Action;
use App\Support\Subscriptions\AllowedWhenReadOnly;
use Illuminate\Support\Carbon;

/**
 * Moves the end of one tenant's trial (FR-TNT-03), for example to give a
 * pilot kost more time. The trial runs to the end of that day in the
 * tenant's time zone.
 */
final class ChangeTrialEnd extends Action implements AllowedWhenReadOnly
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Tenant $tenant, array $input): Tenant
    {
        $this->authorize('manageTrial', $tenant);

        $data = $this->validate($input, [
            'trial_ends_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->transaction(function () use ($tenant, $data): Tenant {
            $old = $tenant->trial_ends_at?->toIso8601String();

            $endsAt = Carbon::parse($data['trial_ends_on'], $tenant->default_timezone)->endOfDay()->utc();

            $tenant->trial_ends_at = $endsAt;
            $tenant->save();

            TenantChangedByPlatform::dispatch(
                $tenant,
                'tenant.trial_changed',
                ['trial_ends_at' => $old],
                ['trial_ends_at' => $endsAt->toIso8601String()],
                $data['reason'] ?? null,
            );

            return $tenant;
        });
    }
}
