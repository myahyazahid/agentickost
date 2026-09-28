<?php

namespace App\Modules\Maintenance\Database\Factories;

use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\Models\TicketUpdate;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\ActorType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketUpdate>
 */
class TicketUpdateFactory extends Factory
{
    protected $model = TicketUpdate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'ticket_id' => fn (array $attributes) => Ticket::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'actor_type' => ActorType::System,
            'note' => 'Catatan',
        ];
    }
}
