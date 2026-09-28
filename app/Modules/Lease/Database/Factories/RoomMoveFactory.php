<?php

namespace App\Modules\Lease\Database\Factories;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\RoomMove;
use App\Modules\Property\Models\Room;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomMove>
 */
class RoomMoveFactory extends Factory
{
    protected $model = RoomMove::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'contract_id' => fn (array $attributes) => Contract::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'from_room_id' => fn (array $attributes) => Contract::query()->withoutGlobalScopes()->whereKey($attributes['contract_id'])->value('room_id'),
            'to_room_id' => fn (array $attributes) => Room::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'moved_on' => '2026-09-15',
            'old_rent_amount' => 1_200_000,
            'new_rent_amount' => 1_500_000,
            'deposit_difference_amount' => 0,
        ];
    }
}
