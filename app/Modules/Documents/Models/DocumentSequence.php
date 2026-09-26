<?php

namespace App\Modules\Documents\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Documents\Database\Factories\DocumentSequenceFactory;
use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Enums\ResetPeriod;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property DocumentType $document_type
 * @property string $format
 * @property ResetPeriod $reset_period
 * @property string $current_period_key
 * @property int $next_number
 */
#[Fillable(['document_type', 'format', 'reset_period', 'current_period_key', 'next_number'])]
#[UseFactory(DocumentSequenceFactory::class)]
class DocumentSequence extends Model
{
    /** @use HasFactory<DocumentSequenceFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'reset_period' => ResetPeriod::class,
            'next_number' => 'integer',
        ];
    }
}
