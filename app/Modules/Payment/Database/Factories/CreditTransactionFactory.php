<?php

namespace App\Modules\Payment\Database\Factories;

use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Enums\CreditTransactionType;
use App\Modules\Payment\Models\CreditTransaction;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\ActorType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    protected $model = CreditTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'contract_id' => fn (array $attributes) => Contract::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'type' => CreditTransactionType::Opening,
            'amount' => 100_000,
            'occurred_on' => '2026-09-20',
            'created_by_type' => ActorType::System,
        ];
    }
}
