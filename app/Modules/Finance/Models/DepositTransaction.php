<?php

namespace App\Modules\Finance\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Documents\Concerns\HasAttachments;
use App\Modules\Finance\Database\Factories\DepositTransactionFactory;
use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Models\PaymentAllocation;
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
 * One entry of a contract's deposit ledger (FR-DEP-01). The deposit held is
 * the sum of the entries. A deposit is owed to the resident, not income
 * (PRD §8.6); entries are never changed.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $contract_id
 * @property DepositTransactionType $type
 * @property int $amount
 * @property string|null $reason
 * @property string|null $payment_allocation_id
 * @property string|null $invoice_id
 * @property string|null $related_contract_id
 * @property string|null $account_id
 * @property string|null $settlement_id
 * @property Carbon $occurred_on
 * @property string|null $created_by
 * @property Carbon $created_at
 */
#[Fillable([
    'contract_id', 'type', 'amount', 'reason', 'payment_allocation_id', 'invoice_id', 'related_contract_id',
    'account_id', 'occurred_on', 'created_by',
])]
#[UseFactory(DepositTransactionFactory::class)]
class DepositTransaction extends Model
{
    /** @use HasFactory<DepositTransactionFactory> */
    use Auditable, BelongsToTenant, HasAttachments, HasFactory, HasUlids;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Mutasi deposit tidak dapat diubah.'));
        static::deleting(fn (): never => throw new LogicException('Mutasi deposit tidak dapat dihapus.'));
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * The other contract of a transfer.
     *
     * @return BelongsTo<Contract, $this>
     */
    public function relatedContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'related_contract_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<PaymentAllocation, $this>
     */
    public function paymentAllocation(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocation::class);
    }

    /**
     * Cash or bank account a refund was paid from.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => DepositTransactionType::class,
            'amount' => RupiahCast::class,
            'occurred_on' => 'date',
        ];
    }
}
