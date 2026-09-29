<?php

namespace App\Modules\Subscription\Database\Factories;

use App\Modules\Subscription\Enums\UsageMetric;
use App\Modules\Subscription\Models\UsageCounter;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageCounter>
 */
class UsageCounterFactory extends Factory
{
    protected $model = UsageCounter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'metric' => UsageMetric::Messages,
            'period' => '2026-10',
            'used' => 0,
        ];
    }
}
