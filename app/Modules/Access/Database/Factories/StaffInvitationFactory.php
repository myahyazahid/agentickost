<?php

namespace App\Modules\Access\Database\Factories;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\StaffInvitation;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StaffInvitation>
 */
class StaffInvitationFactory extends Factory
{
    protected $model = StaffInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Caretaker,
            'property_ids' => [],
            'token_hash' => StaffInvitation::hashToken(Str::random(40)),
            'expires_at' => now()->addDays(StaffInvitation::VALID_DAYS),
        ];
    }
}
