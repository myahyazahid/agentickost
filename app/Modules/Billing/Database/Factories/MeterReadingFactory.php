<?php

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\UtilityKind;
use App\Modules\Billing\Models\MeterReading;
use App\Modules\Property\Models\Room;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeterReading>
 */
class MeterReadingFactory extends Factory
{
    protected $model = MeterReading::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'room_id' => fn (array $attributes) => Room::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'utility' => UtilityKind::Electricity,
            'reading_date' => fake()->unique()->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d'),
            'previous_value' => '100.00',
            'current_value' => '150.00',
            'rate_amount' => 1_500,
            'amount' => 75_000,
        ];
    }
}
