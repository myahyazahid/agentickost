<?php

namespace App\Modules\Property\Database\Factories;

use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Models\RoomPrice;
use App\Modules\Property\Models\RoomType;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomPrice>
 */
class RoomPriceFactory extends Factory
{
    protected $model = RoomPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'room_type_id' => fn (array $attributes) => RoomType::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'room_id' => null,
            'rental_period' => RentalPeriod::Monthly,
            'amount' => 1_200_000,
            'effective_from' => '2026-01-01',
        ];
    }
}
