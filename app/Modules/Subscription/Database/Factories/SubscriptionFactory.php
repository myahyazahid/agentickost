<?php

namespace App\Modules\Subscription\Database\Factories;

use App\Modules\Subscription\Models\Subscription;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A fresh tenant already gets a trial subscription, so this factory builds
 * one for a tenant of its own unless one is given.
 *
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
        ];
    }
}
