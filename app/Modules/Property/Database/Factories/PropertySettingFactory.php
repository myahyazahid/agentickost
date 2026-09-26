<?php

namespace App\Modules\Property\Database\Factories;

use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\PropertySetting;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertySetting>
 */
class PropertySettingFactory extends Factory
{
    protected $model = PropertySetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'property_id' => fn (array $attributes) => Property::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            ...PropertySetting::defaults(),
        ];
    }
}
