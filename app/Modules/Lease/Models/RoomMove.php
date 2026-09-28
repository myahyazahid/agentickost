<?php

namespace App\Modules\Lease\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Billing\Models\CreditNote;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Lease\Database\Factories\RoomMoveFactory;
use App\Modules\Property\Models\Room;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A contract moving to another room mid-period (FR-SIK-02, PRD §8.8). The
 * contract always points at the current room; this is the history.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $contract_id
 * @property string $from_room_id
 * @property string $to_room_id
 * @property Carbon $moved_on
 * @property int $old_rent_amount
 * @property int $new_rent_amount
 * @property int $deposit_difference_amount
 * @property string|null $invoice_id
 * @property string|null $notes
 * @property string|null $created_by
 */
#[Fillable([
    'contract_id', 'from_room_id', 'to_room_id', 'moved_on', 'old_rent_amount', 'new_rent_amount',
    'deposit_difference_amount', 'invoice_id', 'notes', 'created_by',
])]
#[UseFactory(RoomMoveFactory::class)]
class RoomMove extends Model
{
    /** @use HasFactory<RoomMoveFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    protected static function booted(): void
    {
        static::updating(function (self $move): void {
            if (array_diff(array_keys($move->getDirty()), ['invoice_id', 'updated_at']) !== []) {
                throw new LogicException('Riwayat pindah kamar tidak dapat diubah.');
            }
        });

        static::deleting(fn (): never => throw new LogicException('Riwayat pindah kamar tidak dapat dihapus.'));
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    /**
     * The invoice for the new room's share of the period.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Credit for the old room's unused days.
     *
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'moved_on' => 'date',
            'old_rent_amount' => RupiahCast::class,
            'new_rent_amount' => RupiahCast::class,
            'deposit_difference_amount' => RupiahCast::class,
        ];
    }
}
