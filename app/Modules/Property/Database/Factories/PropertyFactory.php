<?php

namespace App\Modules\Property\Database\Factories;

use App\Modules\Property\Enums\GenderPolicy;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Timezone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'name' => 'Kost '.fake()->streetName(),
            'code' => strtoupper(fake()->unique()->bothify('???##')),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'province' => fake()->randomElement(['DKI Jakarta', 'Jawa Barat', 'Jawa Tengah', 'DI Yogyakarta', 'Jawa Timur', 'Bali']),
            'postal_code' => fake()->postcode(),
            'timezone' => Timezone::Wib,
            'gender_policy' => GenderPolicy::Mixed,
        ];
    }
}
