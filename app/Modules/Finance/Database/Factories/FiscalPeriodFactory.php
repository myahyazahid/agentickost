<?php

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Finance\Models\FiscalPeriod;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalPeriod>
 */
class FiscalPeriodFactory extends Factory
{
    protected $model = FiscalPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'year' => fake()->unique()->numberBetween(2000, 2099),
            'month' => 1,
        ];
    }
}
