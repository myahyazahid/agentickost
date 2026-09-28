<?php

namespace App\Modules\Payment\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Access\Models\User;
use App\Modules\Finance\Models\Account;
use App\Modules\Payment\Database\Factories\StaffCashHandoverFactory;
use App\Modules\Payment\States\Handover\Confirmed;
use App\Modules\Payment\States\Handover\HandoverState;
use App\Modules\Property\Concerns\BelongsToProperty;
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
 * Cash a staff member hands over to the owner (FR-PAY-08, PRD §8.12). The
 * expected amount is what the staff member held at that moment; any
 * difference is flagged and needs an explanation before it is confirmed.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $property_id
 * @property string $staff_user_id
 * @property int $expected_amount
 * @property int $actual_amount
 * @property int $difference_amount
 * @property string $destination_account_id
 * @property HandoverState $status
 * @property string|null $difference_note
 * @property string|null $dispute_note
 * @property Carbon $handed_over_at
 * @property string|null $confirmed_by
 * @property Carbon|null $confirmed_at
 */
#[Fillable([
    'property_id', 'staff_user_id', 'expected_amount', 'actual_amount', 'destination_account_id', 'handed_over_at',
])]
#[UseFactory(StaffCashHandoverFactory::class)]
class StaffCashHandover extends Model
{
    /** @use HasFactory<StaffCashHandoverFactory> */
    use Auditable, BelongsToProperty, BelongsToTenant, EnforcesStateTransitions, HasFactory, HasStates, HasUlids;

    /**
     * Set when the handover is recorded; the balance it was checked against
     * must not move afterwards.
     */
    private const FIXED = ['property_id', 'staff_user_id', 'expected_amount'];

    protected static function booted(): void
    {
        static::updating(function (self $handover): void {
            if ($handover->getRawOriginal('status') === Confirmed::$name) {
                throw new LogicException('Setoran yang sudah diterima tidak dapat diubah.');
            }

            if (array_intersect(array_keys($handover->getDirty()), self::FIXED) !== []) {
                throw new LogicException('Staf, properti, dan saldo yang diharapkan pada setoran tidak dapat diubah.');
            }
        });

        static::deleting(fn (): never => throw new LogicException('Setoran kas tidak dapat dihapus.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Uses the loaded property when there is one (NFR-LOC-02).
     */
    public function propertyTimezone(): string
    {
        return ($this->property ?? $this->property()->firstOrFail())->timezone->value;
    }

    public function hasDifference(): bool
    {
        return $this->actual_amount !== $this->expected_amount;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => HandoverState::class,
            'expected_amount' => RupiahCast::class,
            'actual_amount' => RupiahCast::class,
            'difference_amount' => RupiahCast::class,
            'handed_over_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }
}
