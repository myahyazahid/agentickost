<?php

namespace App\Modules\Documents\Database\Factories;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\ActorType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'attachable_type' => 'tenant',
            'attachable_id' => fn (array $attributes) => $attributes['tenant_id'],
            'collection' => AttachmentCollection::Photo,
            'disk' => 'local',
            'path' => fn (array $attributes) => "tenants/{$attributes['tenant_id']}/uploads/photo/".fake()->uuid().'.jpg',
            'original_name' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'is_encrypted' => false,
            'uploaded_by_type' => ActorType::System,
        ];
    }
}
