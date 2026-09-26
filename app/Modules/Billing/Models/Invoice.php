<?php

namespace App\Modules\Billing\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Billing\Database\Factories\InvoiceFactory;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\States\Invoice\Draft;
use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Payer;
use App\Modules\Property\Concerns\BelongsToProperty;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Actors\ActorType;
use App\Support\Money\RupiahCast;
use App\Support\States\EnforcesStateTransitions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;
use Spatie\ModelStates\HasStates;

/**
 * A bill to a payer (FR-BIL-02). Once issued, only its status and running
 * totals change, through Actions; corrections go through void or credit
 * notes (FR-BIL-06, PRD §8.10).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $property_id
 * @property string|null $contract_id
 * @property string $payer_id
 * @property string|null $number
 * @property InvoiceType $type
 * @property InvoiceState $status
 * @property string|null $generation_key
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon|null $issue_date
 * @property Carbon $due_date
 * @property int $items_total_amount
 * @property int $penalty_amount
 * @property int $paid_amount
 * @property int $credited_amount
 * @property int $balance_amount
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property ActorType|null $issued_by_type
 * @property string|null $issued_by_id
 */
#[Fillable([
    'property_id', 'contract_id', 'payer_id', 'type', 'generation_key', 'period_start', 'period_end',
    'issue_date', 'due_date', 'items_total_amount',
])]
#[UseFactory(InvoiceFactory::class)]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use Auditable, BelongsToProperty, BelongsToTenant, EnforcesStateTransitions, HasFactory, HasStates, HasUlids;

    /**
     * Columns that may still change after the invoice leaves draft.
     */
    private const MUTABLE_AFTER_ISSUE = [
        'status', 'penalty_amount', 'paid_amount', 'credited_amount', 'voided_at', 'void_reason',
        'generation_key', 'updated_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'items_total_amount' => 0,
        'penalty_amount' => 0,
        'paid_amount' => 0,
        'credited_amount' => 0,
    ];

    protected static function booted(): void
    {
        static::updating(function (self $invoice): void {
            if ($invoice->getRawOriginal('status') === Draft::$name) {
                return;
            }

            $locked = array_diff(array_keys($invoice->getDirty()), self::MUTABLE_AFTER_ISSUE);

            if ($locked !== []) {
                throw new LogicException('Tagihan yang sudah terbit tidak dapat diubah: '.implode(', ', $locked).'.');
            }
        });

        static::deleting(function (self $invoice): void {
            if (! $invoice->status->equals(Draft::class)) {
                throw new LogicException('Tagihan yang sudah terbit tidak dapat dihapus. Batalkan dengan void atau nota kredit.');
            }
        });
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Payer, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<PenaltyAccrual, $this>
     */
    public function penalties(): HasMany
    {
        return $this->hasMany(PenaltyAccrual::class);
    }

    /**
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * Late: past the due date in the property's time zone and not settled
     * (PRD §9.4). Computed, never stored.
     */
    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->balance_amount > 0
            && $this->due_date->lessThan($this->lateAsOf());
    }

    /**
     * Whole days past the due date, or 0 when the invoice is not late.
     */
    public function daysLate(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays($this->lateAsOf()) : 0;
    }

    /**
     * Today in the property's time zone. Uses the loaded property when there
     * is one, so lists do not query it per row.
     */
    private function lateAsOf(): CarbonImmutable
    {
        $property = $this->relationLoaded('property') ? $this->getRelation('property') : null;

        return ($property instanceof Property ? $property : $this->property()->firstOrFail())->today();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'status' => InvoiceState::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'items_total_amount' => RupiahCast::class,
            'penalty_amount' => RupiahCast::class,
            'paid_amount' => RupiahCast::class,
            'credited_amount' => RupiahCast::class,
            'balance_amount' => RupiahCast::class,
            'voided_at' => 'datetime',
            'issued_by_type' => ActorType::class,
        ];
    }
}
