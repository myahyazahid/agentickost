<?php

namespace App\Modules\Subscription\Database\Factories;

use App\Modules\Subscription\Enums\BillingCycle;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionInvoice;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionInvoice>
 */
class SubscriptionInvoiceFactory extends Factory
{
    protected $model = SubscriptionInvoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'subscription_id' => fn (array $attributes) => Subscription::query()->withoutGlobalScopes()->where('tenant_id', $attributes['tenant_id'])->value('id')
                ?? Subscription::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'plan_id' => Plan::factory(),
            'number' => 'LGN-'.Str::upper(Str::random(10)),
            'billing_cycle' => BillingCycle::Monthly,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'amount' => 150_000,
            'due_date' => '2026-10-01',
        ];
    }
}
