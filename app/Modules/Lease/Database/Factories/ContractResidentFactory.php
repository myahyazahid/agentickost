<?php

namespace App\Modules\Lease\Database\Factories;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractResident;
use App\Modules\Lease\Models\Resident;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractResident>
 */
class ContractResidentFactory extends Factory
{
    protected $model = ContractResident::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'contract_id' => fn (array $attributes) => Contract::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'resident_id' => fn (array $attributes) => Resident::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'is_primary' => false,
            'joined_on' => '2026-09-01',
        ];
    }
}
