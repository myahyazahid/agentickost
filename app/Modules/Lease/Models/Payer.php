<?php

namespace App\Modules\Lease\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Lease\Database\Factories\PayerFactory;
use App\Modules\Lease\Enums\PayerRelation;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Who is billed for a contract (FR-PNH-03). When residents pay for
 * themselves, resident_id is set and relation is "self".
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $resident_id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property PayerRelation $relation
 * @property Carbon|null $anonymized_at
 */
#[Fillable(['resident_id', 'name', 'phone', 'email', 'relation'])]
#[UseFactory(PayerFactory::class)]
class Payer extends Model
{
    /** @use HasFactory<PayerFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'relation' => PayerRelation::class,
            'anonymized_at' => 'datetime',
        ];
    }
}
