<?php

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Enums\BankAccountKind;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'kind' => BankAccountKind::Bank,
            'provider_name' => fake()->randomElement(['BCA', 'BRI', 'Mandiri', 'BNI']),
            'account_number' => fake()->numerify('##########'),
            'account_holder' => fake()->name(),
            'ledger_account_id' => fn (array $attributes) => Account::factory()->state([
                'tenant_id' => $attributes['tenant_id'],
                'type' => AccountType::Asset,
            ]),
        ];
    }
}
