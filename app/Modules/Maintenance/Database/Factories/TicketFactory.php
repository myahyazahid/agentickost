<?php

namespace App\Modules\Maintenance\Database\Factories;

use App\Modules\Maintenance\Enums\TicketCategory;
use App\Modules\Maintenance\Enums\TicketPriority;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\ActorType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * New tickets; move them along through the Maintenance actions in tests.
 *
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'property_id' => fn (array $attributes) => Property::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'reported_by_type' => ActorType::System,
            'category' => TicketCategory::Plumbing,
            'title' => 'Keran kamar mandi bocor',
            'description' => 'Air menetes terus dari keran.',
            'priority' => TicketPriority::Normal,
        ];
    }
}
