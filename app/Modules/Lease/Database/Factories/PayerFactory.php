<?php

namespace App\Modules\Lease\Database\Factories;

use App\Modules\Lease\Enums\PayerRelation;
use App\Modules\Lease\Models\Payer;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payer>
 */
class PayerFactory extends Factory
{
    protected $model = Payer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'name' => fake()->name(),
            'phone' => '+628'.fake()->unique()->numerify('##########'),
            'relation' => PayerRelation::Parent,
        ];
    }
}
