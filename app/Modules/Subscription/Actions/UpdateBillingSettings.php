<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Subscription\Support\BillingSettings;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\PlatformSettings;
use App\Support\Actions\Action;

/**
 * How long unpaid subscriptions stay in grace and read-only, and where
 * tenants transfer their subscription payments. Applies from the next
 * daily run; a grace period already running keeps its end.
 */
final class UpdateBillingSettings extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): void
    {
        $this->authorize('manageSettings', Tenant::class);

        $data = $this->validate($input, [
            'grace_days' => ['required', 'integer', 'between:0,60'],
            'read_only_days' => ['required', 'integer', 'between:1,365'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->transaction(function () use ($data): void {
            PlatformSettings::put(BillingSettings::GRACE_DAYS, (int) $data['grace_days']);
            PlatformSettings::put(BillingSettings::READ_ONLY_DAYS, (int) $data['read_only_days']);
            PlatformSettings::put(BillingSettings::PAYMENT_INSTRUCTIONS, $data['payment_instructions'] ?? null);
        });
    }
}
