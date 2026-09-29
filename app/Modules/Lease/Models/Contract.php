<?php

namespace App\Modules\Lease\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Lease\Database\Factories\ContractFactory;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Payment\Models\CreditTransaction;
use App\Modules\Property\Concerns\BelongsToProperty;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Models\Room;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use App\Support\States\EnforcesStateTransitions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\ModelStates\HasStates;

/**
 * A rental agreement for one room (FR-KTR-01). The rent is locked at signing:
 * later room price changes do not touch it (FR-KMR-03).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $property_id
 * @property string $room_id
 * @property string $payer_id
 * @property string|null $number
 * @property ContractState $status
 * @property RentalPeriod $rental_period
 * @property int $rent_amount
 * @property int $deposit_amount
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property int $billing_anchor_day
 * @property Carbon $next_period_start
 * @property bool $notify_resident
 * @property bool $notify_payer
 * @property int|null $early_termination_penalty_amount
 * @property int|null $termination_penalty_amount
 * @property Carbon|null $notice_given_on
 * @property Carbon|null $planned_move_out_on
 * @property Carbon|null $ended_on
 * @property string|null $termination_reason
 * @property string|null $renewed_from_contract_id
 * @property string|null $clauses
 * @property Carbon|null $end_reminder_sent_at
 * @property Carbon|null $imported_at Set for contracts carried in at onboarding (FR-ONB-02)
 * @property string|null $created_by
 */
#[Fillable([
    'property_id', 'room_id', 'payer_id', 'rental_period', 'rent_amount', 'deposit_amount',
    'start_date', 'end_date', 'billing_anchor_day', 'next_period_start', 'notify_resident',
    'notify_payer', 'early_termination_penalty_amount', 'termination_penalty_amount',
    'notice_given_on', 'planned_move_out_on', 'ended_on', 'termination_reason',
    'renewed_from_contract_id', 'clauses', 'created_by', 'imported_at',
])]
#[UseFactory(ContractFactory::class)]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use Auditable, BelongsToProperty, BelongsToTenant, EnforcesStateTransitions, HasFactory, HasStates, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'notify_resident' => true,
        'notify_payer' => true,
    ];

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Payer, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    /**
     * @return BelongsToMany<Resident, $this, ContractResident>
     */
    public function residents(): BelongsToMany
    {
        return $this->belongsToMany(Resident::class, 'contract_residents')
            ->using(ContractResident::class)
            ->withPivot(['id', 'is_primary', 'share_type', 'share_amount', 'joined_on', 'left_on']);
    }

    /**
     * @return HasMany<ContractResident, $this>
     */
    public function occupants(): HasMany
    {
        return $this->hasMany(ContractResident::class);
    }

    /**
     * @return HasMany<ContractHold, $this>
     */
    public function holds(): HasMany
    {
        return $this->hasMany(ContractHold::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_contract_id');
    }

    /**
     * @return HasOne<self, $this>
     */
    public function renewal(): HasOne
    {
        return $this->hasOne(self::class, 'renewed_from_contract_id');
    }

    /**
     * Deposit ledger (FR-DEP-01), written by the Finance module.
     *
     * @return HasMany<DepositTransaction, $this>
     */
    public function depositTransactions(): HasMany
    {
        return $this->hasMany(DepositTransaction::class);
    }

    /**
     * Credit balance ledger (FR-PAY-05), written by the Payment module.
     *
     * @return HasMany<CreditTransaction, $this>
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * @return HasMany<RoomMove, $this>
     */
    public function roomMoves(): HasMany
    {
        return $this->hasMany(RoomMove::class)->orderBy('moved_on');
    }

    /**
     * @return HasMany<Inspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    /**
     * @return HasOne<Settlement, $this>
     */
    public function settlement(): HasOne
    {
        return $this->hasOne(Settlement::class);
    }

    public function primaryResident(): ?Resident
    {
        return $this->residents()->wherePivot('is_primary', true)->first();
    }

    /**
     * Whether the contract was already running before the tenant started
     * using Agentic Kost. Its deposit comes in with the opening balance.
     */
    public function isImported(): bool
    {
        return $this->imported_at !== null;
    }

    public function isRunning(): bool
    {
        return $this->status->isRunning();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => ContractState::class,
            'rental_period' => RentalPeriod::class,
            'rent_amount' => RupiahCast::class,
            'deposit_amount' => RupiahCast::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'billing_anchor_day' => 'integer',
            'next_period_start' => 'date',
            'notify_resident' => 'boolean',
            'notify_payer' => 'boolean',
            'early_termination_penalty_amount' => RupiahCast::class,
            'termination_penalty_amount' => RupiahCast::class,
            'notice_given_on' => 'date',
            'planned_move_out_on' => 'date',
            'ended_on' => 'date',
            'end_reminder_sent_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }
}
