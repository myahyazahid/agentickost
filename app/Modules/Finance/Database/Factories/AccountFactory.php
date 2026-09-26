<?php

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Models\Account;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'code' => '9-'.fake()->unique()->numerify('####'),
            'name' => 'Beban '.fake()->word(),
            'type' => AccountType::Expense,
        ];
    }
}
