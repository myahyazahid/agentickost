<?php

namespace App\Modules\Payment\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Access\Models\User;
use App\Modules\Documents\Concerns\HasAttachments;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Payer;
use App\Modules\Payment\Database\Factories\PaymentFactory;
use App\Modules\Payment\Enums\PaymentChannel;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\States\Payment\PaymentState;
use App\Modules\Payment\States\Payment\Pending;
use App\Modules\Property\Concerns\BelongsToProperty;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Actors\ActorType;
use App\Support\Money\RupiahCast;
use App\Support\States\EnforcesStateTransitions;
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
 * Money received for a contract (FR-PAY-01). A pending payment waits for
 * verification; once verified it only changes by being reversed, and it is
 * never deleted (PRD §8.10).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $property_id
 * @property string|null $payer_id
 * @property string|null $contract_id
 * @property string|null $booking_id
 * @property string|null $receipt_number
 * @property PaymentMethod $method
 * @property PaymentChannel $channel
 * @property PaymentState $status
 * @property int $amount
 * @property Carbon $paid_at
 * @property string|null $bank_account_id
 * @property string|null $received_by_user_id
 * @property string|null $reference
 * @property string|null $gateway_transaction_id
 * @property string|null $rejection_reason
 * @property ActorType|null $verified_by_type
 * @property string|null $verified_by_id
 * @property Carbon|null $verified_at
 * @property Carbon|null $reversed_at
 * @property string|null $reversal_reason
 * @property Carbon $created_at
 */
#[Fillable([
    'property_id', 'payer_id', 'contract_id', 'method', 'channel', 'amount', 'paid_at',
    'bank_account_id', 'received_by_user_id', 'reference',
])]
#[UseFactory(PaymentFactory::class)]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use Auditable, BelongsToProperty, BelongsToTenant, EnforcesStateTransitions, HasAttachments, HasFactory, HasStates, HasUlids;

    /**
     * Columns that may still change once the payment left pending.
     */
    private const MUTABLE_AFTER_REVIEW = ['status', 'reversed_at', 'reversal_reason', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (self $payment): void {
            if ($payment->getRawOriginal('status') === Pending::$name) {
                return;
            }

            $locked = array_diff(array_keys($payment->getDirty()), self::MUTABLE_AFTER_REVIEW);

            if ($locked !== []) {
                throw new LogicException('Pembayaran yang sudah diperiksa tidak dapat diubah: '.implode(', ', $locked).'.');
            }
        });

        static::deleting(fn (): never => throw new LogicException('Pembayaran tidak dapat dihapus. Tolak atau balik pembayarannya.'));
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
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return HasMany<CreditTransaction, $this>
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * Times are stored in UTC and shown in the property's time zone
     * (NFR-LOC-02). Uses the loaded property when there is one.
     */
    public function propertyTimezone(): string
    {
        return ($this->property ?? $this->property()->firstOrFail())->timezone->value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'channel' => PaymentChannel::class,
            'status' => PaymentState::class,
            'amount' => RupiahCast::class,
            'paid_at' => 'datetime',
            'verified_by_type' => ActorType::class,
            'verified_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }
}
