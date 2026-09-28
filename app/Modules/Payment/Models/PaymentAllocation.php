<?php

namespace App\Modules\Payment\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Payment\Database\Factories\PaymentAllocationFactory;
use App\Modules\Property\Enums\AllocationCategory;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Part of an invoice component paid from one source: a payment, the credit
 * balance, or the deposit (PRD §8.5). Only ever marked reversed; a changed
 * amount is a new allocation.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $payment_id
 * @property string|null $credit_transaction_id
 * @property string|null $deposit_transaction_id
 * @property string $invoice_id
 * @property AllocationCategory $allocation_category
 * @property int $amount
 * @property Carbon|null $reversed_at
 * @property Carbon $created_at
 */
#[Fillable(['payment_id', 'credit_transaction_id', 'deposit_transaction_id', 'invoice_id', 'allocation_category', 'amount'])]
#[UseFactory(PaymentAllocationFactory::class)]
class PaymentAllocation extends Model
{
    /** @use HasFactory<PaymentAllocationFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    protected static function booted(): void
    {
        static::updating(function (self $allocation): void {
            $changed = array_diff(array_keys($allocation->getDirty()), ['reversed_at', 'updated_at']);

            if ($changed !== [] || $allocation->getRawOriginal('reversed_at') !== null) {
                throw new LogicException('Alokasi pembayaran tidak dapat diubah, hanya dibalik.');
            }
        });

        static::deleting(fn (): never => throw new LogicException('Alokasi pembayaran tidak dapat dihapus.'));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('reversed_at');
    }

    public function isActive(): bool
    {
        return $this->reversed_at === null;
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<CreditTransaction, $this>
     */
    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class);
    }

    /**
     * @return BelongsTo<DepositTransaction, $this>
     */
    public function depositTransaction(): BelongsTo
    {
        return $this->belongsTo(DepositTransaction::class);
    }

    /**
     * Where the money came from, for lists.
     */
    public function sourceLabel(): string
    {
        return match (true) {
            $this->payment_id !== null => 'Pembayaran',
            $this->credit_transaction_id !== null => 'Saldo kredit',
            default => 'Deposit',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'allocation_category' => AllocationCategory::class,
            'amount' => RupiahCast::class,
            'reversed_at' => 'datetime',
        ];
    }
}
