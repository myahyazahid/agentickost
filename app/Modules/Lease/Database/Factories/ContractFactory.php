<?php

namespace App\Modules\Lease\Database\Factories;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractResident;
use App\Modules\Lease\Models\Payer;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Models\Room;
use App\Modules\Property\States\Room\Occupied;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Contracts come with one primary resident, like real ones.
 *
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'room_id' => fn (array $attributes) => Room::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'property_id' => fn (array $attributes) => Room::query()->withoutGlobalScopes()->whereKey($attributes['room_id'])->firstOrFail()->property_id,
            'payer_id' => fn (array $attributes) => Payer::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'rental_period' => RentalPeriod::Monthly,
            'rent_amount' => 1_200_000,
            'deposit_amount' => 1_200_000,
            'start_date' => '2026-09-01',
            'billing_anchor_day' => 1,
            'next_period_start' => '2026-09-01',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Contract $contract): void {
            if (ContractResident::query()->withoutGlobalScopes()->where('contract_id', $contract->id)->exists()) {
                return;
            }

            ContractResident::factory()->create([
                'tenant_id' => $contract->tenant_id,
                'contract_id' => $contract->id,
                'resident_id' => Resident::factory()->create(['tenant_id' => $contract->tenant_id])->id,
                'is_primary' => true,
                'joined_on' => $contract->start_date,
            ]);
        });
    }

    /**
     * A running contract with its room marked occupied.
     */
    public function active(): static
    {
        return $this->state(['status' => Active::$name])->afterCreating(function (Contract $contract): void {
            $room = Room::query()->withoutGlobalScopes()->whereKey($contract->room_id)->firstOrFail();
            $room->status = new Occupied($room);
            $room->saveQuietly();
        });
    }
}
