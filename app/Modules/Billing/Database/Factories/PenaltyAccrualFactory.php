<?php

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PenaltyAccrual;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PenaltyAccrual>
 */
class PenaltyAccrualFactory extends Factory
{
    protected $model = PenaltyAccrual::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'invoice_id' => fn (array $attributes) => Invoice::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'accrued_on' => '2026-10-05',
            'amount' => 10_000,
            'rule_snapshot' => ['type' => 'daily', 'amount' => 10_000],
        ];
    }
}
