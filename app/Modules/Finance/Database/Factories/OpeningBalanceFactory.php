<?php

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Finance\Models\OpeningBalance;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningBalance>
 */
class OpeningBalanceFactory extends Factory
{
    protected $model = OpeningBalance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'cutoff_date' => '2026-09-30',
        ];
    }
}
