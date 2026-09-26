<?php

namespace App\Modules\Access\Database\Factories;

use App\Modules\Access\Models\AuditLog;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\ActorType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'actor_type' => ActorType::System,
            'event' => 'tenant.note_added',
            'subject_type' => 'tenant',
            'subject_id' => (string) Str::ulid(),
        ];
    }
}
