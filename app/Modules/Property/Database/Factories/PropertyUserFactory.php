<?php

namespace App\Modules\Property\Database\Factories;

use App\Modules\Access\Models\User;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\PropertyUser;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyUser>
 */
class PropertyUserFactory extends Factory
{
    protected $model = PropertyUser::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'property_id' => fn (array $attributes) => Property::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'user_id' => fn (array $attributes) => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
        ];
    }
}
