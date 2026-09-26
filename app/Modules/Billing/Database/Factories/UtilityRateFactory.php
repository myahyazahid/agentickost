<?php

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\MeterUnit;
use App\Modules\Billing\Enums\UtilityKind;
use App\Modules\Billing\Enums\UtilityMode;
use App\Modules\Billing\Models\UtilityRate;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UtilityRate>
 */
class UtilityRateFactory extends Factory
{
    protected $model = UtilityRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'property_id' => fn (array $attributes) => Property::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'utility' => UtilityKind::Electricity,
            'mode' => UtilityMode::Metered,
            'unit' => MeterUnit::Kwh,
            'rate_amount' => 1_500,
            'effective_from' => '2026-01-01',
        ];
    }
}
