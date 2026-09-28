<?php

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Finance\Enums\OpeningBalanceKind;
use App\Modules\Finance\Models\OpeningBalance;
use App\Modules\Finance\Models\OpeningBalanceLine;
use App\Modules\Lease\Models\Contract;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningBalanceLine>
 */
class OpeningBalanceLineFactory extends Factory
{
    protected $model = OpeningBalanceLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'opening_balance_id' => fn (array $attributes) => OpeningBalance::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'kind' => OpeningBalanceKind::Deposit,
            'contract_id' => fn (array $attributes) => Contract::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'amount' => 1_200_000,
        ];
    }
}
