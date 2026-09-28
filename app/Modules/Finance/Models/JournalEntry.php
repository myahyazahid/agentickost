<?php

namespace App\Modules\Finance\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Finance\Database\Factories\JournalEntryFactory;
use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Actors\ActorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A double-entry journal posted automatically from a business event
 * (FR-ACC-02). Never changed or deleted; a correction is a reversing entry.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $fiscal_period_id
 * @property string|null $property_id
 * @property string $number
 * @property Carbon $entry_date
 * @property JournalEvent $event
 * @property string $description
 * @property string|null $source_type
 * @property string|null $source_id
 * @property string|null $reversal_of_id
 * @property ActorType $created_by_type
 * @property string|null $created_by_id
 * @property Carbon $created_at
 */
#[Fillable([
    'fiscal_period_id', 'property_id', 'number', 'entry_date', 'event', 'description', 'source_type', 'source_id',
    'reversal_of_id', 'created_by_type', 'created_by_id',
])]
#[UseFactory(JournalEntryFactory::class)]
class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Jurnal tidak dapat diubah. Buat jurnal pembalik.'));
        static::deleting(fn (): never => throw new LogicException('Jurnal tidak dapat dihapus. Buat jurnal pembalik.'));
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<FiscalPeriod, $this>
     */
    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /**
     * @return HasOne<self, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'event' => JournalEvent::class,
            'created_by_type' => ActorType::class,
        ];
    }
}
