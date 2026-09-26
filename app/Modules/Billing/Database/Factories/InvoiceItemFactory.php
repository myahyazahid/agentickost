<?php

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\InvoiceItemType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Property\Enums\AllocationCategory;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'invoice_id' => fn (array $attributes) => Invoice::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'type' => InvoiceItemType::Other,
            'allocation_category' => AllocationCategory::Other,
            'description' => 'Biaya lain',
            'quantity' => 1,
            'unit_amount' => 50_000,
            'amount' => 50_000,
        ];
    }
}
