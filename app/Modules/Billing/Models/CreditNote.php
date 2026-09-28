<?php

namespace App\Modules\Billing\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Billing\Database\Factories\CreditNoteFactory;
use App\Modules\Property\Enums\AllocationCategory;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Reduces what an issued invoice asks for, with a reason (PRD §8.10).
 * Never edited or deleted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $invoice_id
 * @property string $number
 * @property AllocationCategory $allocation_category
 * @property int $amount
 * @property string $reason
 * @property string|null $room_move_id Set when the note credits a room move (PRD §8.8)
 * @property Carbon $issued_on
 * @property string|null $created_by
 */
#[Fillable(['invoice_id', 'room_move_id', 'number', 'allocation_category', 'amount', 'reason', 'issued_on', 'created_by'])]
#[UseFactory(CreditNoteFactory::class)]
class CreditNote extends Model
{
    /** @use HasFactory<CreditNoteFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Nota kredit tidak dapat diubah.'));
        static::deleting(fn (): never => throw new LogicException('Nota kredit tidak dapat dihapus.'));
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'allocation_category' => AllocationCategory::class,
            'amount' => RupiahCast::class,
            'issued_on' => 'date',
        ];
    }
}
