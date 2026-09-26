<?php

namespace App\Modules\Documents\Database\Factories;

use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Enums\ResetPeriod;
use App\Modules\Documents\Models\DocumentSequence;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentSequence>
 */
class DocumentSequenceFactory extends Factory
{
    protected $model = DocumentSequence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'document_type' => DocumentType::Contract,
            'format' => DocumentType::Contract->defaultFormat(),
            'reset_period' => ResetPeriod::Yearly,
            'current_period_key' => now()->format('Y'),
            'next_number' => 1,
        ];
    }
}
