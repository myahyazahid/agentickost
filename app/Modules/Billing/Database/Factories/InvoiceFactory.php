<?php

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Lease\Models\Contract;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Draft invoices; issue them through the Billing actions in tests.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'contract_id' => fn (array $attributes) => Contract::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'property_id' => fn (array $attributes) => self::contract($attributes)->property_id,
            'payer_id' => fn (array $attributes) => self::contract($attributes)->payer_id,
            'type' => InvoiceType::Adhoc,
            'due_date' => '2026-10-01',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function contract(array $attributes): Contract
    {
        return Contract::query()->withoutGlobalScopes()->whereKey($attributes['contract_id'])->firstOrFail();
    }
}
