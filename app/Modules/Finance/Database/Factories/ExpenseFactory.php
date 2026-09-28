<?php

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\Expense;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'property_id' => fn (array $attributes) => Property::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'expense_account_id' => fn (array $attributes) => Account::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'paid_from_account_id' => fn (array $attributes) => Account::factory()->state([
                'tenant_id' => $attributes['tenant_id'],
                'type' => AccountType::Asset,
                'subtype' => AccountSubtype::Cash,
            ]),
            'amount' => 150_000,
            'spent_on' => '2026-09-20',
            'description' => 'Beli lampu lorong',
        ];
    }
}
