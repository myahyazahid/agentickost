<?php

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalLine>
 */
class JournalLineFactory extends Factory
{
    protected $model = JournalLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'journal_entry_id' => fn (array $attributes) => JournalEntry::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'account_id' => fn (array $attributes) => Account::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'debit_amount' => 100_000,
        ];
    }
}
