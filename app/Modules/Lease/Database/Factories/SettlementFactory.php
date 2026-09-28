<?php

namespace App\Modules\Lease\Database\Factories;

use App\Modules\Lease\Enums\InspectionType;
use App\Modules\Lease\Enums\RoomAfterCheckOut;
use App\Modules\Lease\Models\Inspection;
use App\Modules\Lease\Models\Settlement;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Draft settlements; finalize them through the Lease actions in tests.
 *
 * @extends Factory<Settlement>
 */
class SettlementFactory extends Factory
{
    protected $model = Settlement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'inspection_id' => fn (array $attributes) => Inspection::factory()->state([
                'tenant_id' => $attributes['tenant_id'],
                'type' => InspectionType::CheckOut,
            ]),
            'contract_id' => fn (array $attributes) => Inspection::query()->withoutGlobalScopes()->whereKey($attributes['inspection_id'])->value('contract_id'),
            'moved_out_on' => '2026-10-01',
            'room_after' => RoomAfterCheckOut::Available,
        ];
    }
}
