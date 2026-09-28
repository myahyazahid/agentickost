<?php

namespace App\Modules\Maintenance\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Access\Models\User;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Documents\Concerns\HasAttachments;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\Expense;
use App\Modules\Maintenance\Database\Factories\TicketFactory;
use App\Modules\Maintenance\Enums\TicketCategory;
use App\Modules\Maintenance\Enums\TicketPriority;
use App\Modules\Maintenance\States\Ticket\TicketState;
use App\Modules\Property\Concerns\BelongsToProperty;
use App\Modules\Property\Models\Room;
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
use Spatie\ModelStates\HasStates;

/**
 * A repair or cleaning job for a room or a common area (FR-MNT-01). Its cost
 * becomes an expense, and can be billed to the resident, once the work is
 * confirmed (FR-MNT-04, FR-MNT-05).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $property_id
 * @property string|null $room_id
 * @property ActorType $reported_by_type
 * @property string|null $reported_by_id
 * @property TicketCategory $category
 * @property string $title
 * @property string $description
 * @property TicketPriority $priority
 * @property TicketState $status
 * @property string|null $assigned_user_id
 * @property Carbon|null $due_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $confirmed_at
 * @property int $cost_amount
 * @property string|null $paid_from_account_id
 * @property bool $charge_to_resident
 * @property string|null $charge_invoice_id
 * @property string|null $expense_id
 * @property Carbon $created_at
 */
#[Fillable([
    'property_id', 'room_id', 'reported_by_type', 'reported_by_id', 'category', 'title', 'description', 'priority',
])]
#[UseFactory(TicketFactory::class)]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use Auditable, BelongsToProperty, BelongsToTenant, EnforcesStateTransitions, HasAttachments, HasFactory, HasStates, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cost_amount' => 0,
        'charge_to_resident' => false,
    ];

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function paidFromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function chargeInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'charge_invoice_id');
    }

    /**
     * @return HasMany<TicketUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(TicketUpdate::class)->orderBy('id');
    }

    public function location(): string
    {
        return $this->room_id === null ? 'Area umum' : 'Kamar '.($this->room->number ?? '-');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'reported_by_type' => ActorType::class,
            'category' => TicketCategory::class,
            'priority' => TicketPriority::class,
            'status' => TicketState::class,
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cost_amount' => RupiahCast::class,
            'charge_to_resident' => 'boolean',
        ];
    }
}
