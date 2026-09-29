<?php

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\PlatformSettings;
use App\Support\Actions\Action;

/**
 * Settings that apply to every tenant, such as how long a new tenant's
 * trial lasts (FR-TNT-03). Tenants already registered keep their trial end.
 */
final class UpdatePlatformSettings extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): void
    {
        $this->authorize('manageSettings', Tenant::class);

        $data = $this->validate($input, [
            'trial_days' => ['required', 'integer', 'between:1,365'],
        ]);

        $this->transaction(fn () => PlatformSettings::put(PlatformSettings::TRIAL_DAYS, (int) $data['trial_days']));
    }
}
