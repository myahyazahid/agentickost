<?php

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Models\CreditNote;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Property\Enums\AllocationCategory;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'invoice_id' => fn (array $attributes) => Invoice::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'number' => 'NK/'.fake()->unique()->numerify('####'),
            'allocation_category' => AllocationCategory::Rent,
            'amount' => 100_000,
            'reason' => 'Koreksi',
            'issued_on' => '2026-10-02',
        ];
    }
}
