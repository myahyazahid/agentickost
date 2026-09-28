<?php

namespace App\Modules\Lease\Database\Factories;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Enums\InspectionType;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Inspection;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inspection>
 */
class InspectionFactory extends Factory
{
    protected $model = Inspection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'contract_id' => fn (array $attributes) => Contract::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'room_id' => fn (array $attributes) => Contract::query()->withoutGlobalScopes()->whereKey($attributes['contract_id'])->value('room_id'),
            'type' => InspectionType::CheckIn,
            'inspected_on' => '2026-09-01',
            'inspector_id' => fn (array $attributes) => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
        ];
    }
}
