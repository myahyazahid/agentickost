<?php

namespace App\Modules\Lease\Database\Factories;

use App\Modules\Lease\Enums\ItemCondition;
use App\Modules\Lease\Models\Inspection;
use App\Modules\Lease\Models\InspectionItem;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionItem>
 */
class InspectionItemFactory extends Factory
{
    protected $model = InspectionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'inspection_id' => fn (array $attributes) => Inspection::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'item_name' => 'Kasur',
            'condition' => ItemCondition::Good,
        ];
    }
}
