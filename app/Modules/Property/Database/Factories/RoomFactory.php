<?php

namespace App\Modules\Property\Database\Factories;

use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomType;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'room_type_id' => fn (array $attributes) => RoomType::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'property_id' => fn (array $attributes) => RoomType::query()->withoutGlobalScopes()->whereKey($attributes['room_type_id'])->firstOrFail()->property_id,
            'number' => (string) fake()->unique()->numberBetween(100, 99999),
            'floor' => (string) fake()->numberBetween(1, 4),
            'capacity' => 1,
        ];
    }

    public function forType(RoomType $roomType): static
    {
        return $this->state([
            'tenant_id' => $roomType->tenant_id,
            'room_type_id' => $roomType->id,
            'property_id' => $roomType->property_id,
        ]);
    }
}
