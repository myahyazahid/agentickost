<?php

namespace App\Modules\Payment\Database\Factories;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Property\Enums\AllocationCategory;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'invoice_id' => fn (array $attributes) => Invoice::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'payment_id' => fn (array $attributes) => Payment::factory()->state([
                'tenant_id' => $attributes['tenant_id'],
                'contract_id' => Invoice::query()->withoutGlobalScopes()->whereKey($attributes['invoice_id'])->value('contract_id'),
            ]),
            'allocation_category' => AllocationCategory::Rent,
            'amount' => 100_000,
        ];
    }
}
