<?php

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Lease\Models\Contract;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepositTransaction>
 */
class DepositTransactionFactory extends Factory
{
    protected $model = DepositTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'contract_id' => fn (array $attributes) => Contract::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'type' => DepositTransactionType::Opening,
            'amount' => 1_200_000,
            'occurred_on' => '2026-09-20',
        ];
    }
}
