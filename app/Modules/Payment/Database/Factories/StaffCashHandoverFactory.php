<?php

namespace App\Modules\Payment\Database\Factories;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Models\Account;
use App\Modules\Payment\Models\StaffCashHandover;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffCashHandover>
 */
class StaffCashHandoverFactory extends Factory
{
    protected $model = StaffCashHandover::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'property_id' => fn (array $attributes) => Property::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'staff_user_id' => fn (array $attributes) => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'destination_account_id' => fn (array $attributes) => Account::factory()->state([
                'tenant_id' => $attributes['tenant_id'],
                'type' => AccountType::Asset,
                'subtype' => AccountSubtype::Cash,
            ]),
            'expected_amount' => 500_000,
            'actual_amount' => 500_000,
            'handed_over_at' => '2026-09-30 10:00:00',
        ];
    }
}
