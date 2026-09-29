<?php

namespace App\Modules\Subscription\Database\Factories;

use App\Modules\Subscription\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'paket-'.Str::lower(Str::random(6)),
            'name' => 'Paket '.fake()->unique()->word(),
            'monthly_price_amount' => 150_000,
            'yearly_price_amount' => 1_500_000,
            'max_rooms' => 20,
            'max_properties' => 1,
            'max_staff' => 3,
            'features' => [],
        ];
    }
}
