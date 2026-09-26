<?php

namespace App\Modules\Lease\Database\Factories;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractHold;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractHold>
 */
class ContractHoldFactory extends Factory
{
    protected $model = ContractHold::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'contract_id' => fn (array $attributes) => Contract::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'start_date' => '2027-01-01',
            'end_date' => '2027-02-28',
            'rent_amount' => 500_000,
        ];
    }
}
