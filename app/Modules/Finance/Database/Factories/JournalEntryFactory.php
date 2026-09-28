<?php

namespace App\Modules\Finance\Database\Factories;

use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Models\FiscalPeriod;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\ActorType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'fiscal_period_id' => fn (array $attributes) => FiscalPeriod::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'number' => 'JU/'.fake()->unique()->numerify('#####'),
            'entry_date' => '2026-09-20',
            'event' => JournalEvent::ExpenseRecorded,
            'description' => 'Contoh jurnal',
            'created_by_type' => ActorType::System,
        ];
    }
}
