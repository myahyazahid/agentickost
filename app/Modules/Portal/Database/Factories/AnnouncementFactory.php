<?php

namespace App\Modules\Portal\Database\Factories;

use App\Modules\Portal\Models\Announcement;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'property_id' => fn (array $attributes) => Property::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'title' => 'Pemadaman air hari Sabtu',
            'body' => 'Air mati pukul 09.00 sampai 12.00 untuk pembersihan tandon.',
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['published_at' => null]);
    }
}
