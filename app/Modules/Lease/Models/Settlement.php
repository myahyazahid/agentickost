<?php

namespace App\Modules\Lease\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Lease\Database\Factories\SettlementFactory;
use App\Modules\Lease\Enums\RoomAfterCheckOut;
use App\Modules\Lease\States\Settlement\Draft;
use App\Modules\Lease\States\Settlement\SettlementState;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use App\Support\States\EnforcesStateTransitions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Spatie\ModelStates\HasStates;

/**
 * The final account at check-out (FR-SIK-05, PRD §8.9):
 * arrears + damage + penalty − deposit − credit. Positive is still owed,
 * negative is paid back. Once finalized it never changes.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $contract_id
 * @property string $inspection_id
 * @property SettlementState $status
 * @property Carbon $moved_out_on
 * @property RoomAfterCheckOut $room_after
 * @property int $outstanding_amount
 * @property int $damage_amount
 * @property int $early_termination_amount
 * @property int $deposit_balance_amount
 * @property int $credit_balance_amount
 * @property int $result_amount
 * @property string|null $final_invoice_id
 * @property string|null $refund_account_id
 * @property string|null $finalized_by
 * @property Carbon|null $finalized_at
 */
#[Fillable([
    'contract_id', 'inspection_id', 'moved_out_on', 'room_after', 'outstanding_amount', 'damage_amount',
    'early_termination_amount', 'deposit_balance_amount', 'credit_balance_amount', 'result_amount',
])]
#[UseFactory(SettlementFactory::class)]
class Settlement extends Model
{
    /** @use HasFactory<SettlementFactory> */
    use Auditable, BelongsToTenant, EnforcesStateTransitions, HasFactory, HasStates, HasUlids;

    protected static function booted(): void
    {
        static::updating(function (self $settlement): void {
            if ($settlement->getRawOriginal('status') !== Draft::$name) {
                throw new LogicException('Penyelesaian yang sudah final tidak dapat diubah.');
            }
        });

        static::deleting(fn (): never => throw new LogicException('Penyelesaian check-out tidak dapat dihapus.'));
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Inspection, $this>
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function finalInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'final_invoice_id');
    }

    public function isDraft(): bool
    {
        return $this->status->equals(Draft::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => SettlementState::class,
            'moved_out_on' => 'date',
            'room_after' => RoomAfterCheckOut::class,
            'outstanding_amount' => RupiahCast::class,
            'damage_amount' => RupiahCast::class,
            'early_termination_amount' => RupiahCast::class,
            'deposit_balance_amount' => RupiahCast::class,
            'credit_balance_amount' => RupiahCast::class,
            'result_amount' => RupiahCast::class,
            'finalized_at' => 'datetime',
        ];
    }
}
