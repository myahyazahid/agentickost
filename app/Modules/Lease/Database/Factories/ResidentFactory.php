<?php

namespace App\Modules\Lease\Database\Factories;

use App\Modules\Lease\Enums\Gender;
use App\Modules\Lease\Models\Resident;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    protected $model = Resident::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'full_name' => fake()->name(),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'gender' => fake()->randomElement(Gender::cases()),
        ];
    }
}
