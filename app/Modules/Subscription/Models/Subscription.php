<?php

namespace App\Modules\Subscription\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Subscription\Database\Factories\SubscriptionFactory;
use App\Modules\Subscription\Enums\BillingCycle;
use App\Modules\Subscription\States\Subscription\SubscriptionState;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
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
 * A tenant's one subscription (FR-SUB-01, FR-SUB-04, PRD §9.6). During the
 * trial it has no plan; the trial end lives on the tenant.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $plan_id
 * @property SubscriptionState $status
 * @property BillingCycle|null $billing_cycle
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $grace_ends_at
 * @property Carbon|null $read_only_since
 * @property Carbon|null $cancelled_at
 */
#[Fillable(['plan_id', 'billing_cycle', 'current_period_start', 'current_period_end'])]
#[UseFactory(SubscriptionFactory::class)]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use Auditable, BelongsToTenant, EnforcesStateTransitions, HasFactory, HasStates, HasUlids;

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<SubscriptionInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionState::class,
            'billing_cycle' => BillingCycle::class,
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'grace_ends_at' => 'datetime',
            'read_only_since' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
