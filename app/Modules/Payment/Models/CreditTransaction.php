<?php

namespace App\Modules\Payment\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Database\Factories\CreditTransactionFactory;
use App\Modules\Payment\Enums\CreditTransactionType;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Actors\ActorType;
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
 * One entry of a contract's credit balance (FR-PAY-05). The balance is the
 * sum of the entries; entries are never changed.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $contract_id
 * @property CreditTransactionType $type
 * @property int $amount
 * @property string|null $payment_id
 * @property string|null $booking_id
 * @property string|null $invoice_id
 * @property Carbon $occurred_on
 * @property ActorType $created_by_type
 * @property string|null $created_by_id
 * @property Carbon $created_at
 */
#[Fillable([
    'contract_id', 'type', 'amount', 'payment_id', 'invoice_id', 'occurred_on', 'created_by_type', 'created_by_id',
])]
#[UseFactory(CreditTransactionFactory::class)]
class CreditTransaction extends Model
{
    /** @use HasFactory<CreditTransactionFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Mutasi saldo kredit tidak dapat diubah.'));
        static::deleting(fn (): never => throw new LogicException('Mutasi saldo kredit tidak dapat dihapus.'));
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
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
            'type' => CreditTransactionType::class,
            'amount' => RupiahCast::class,
            'occurred_on' => 'date',
            'created_by_type' => ActorType::class,
        ];
    }
}
