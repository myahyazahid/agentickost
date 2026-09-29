<?php

namespace App\Modules\Subscription\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Subscription\Database\Factories\SubscriptionInvoiceFactory;
use App\Modules\Subscription\Enums\BillingCycle;
use App\Modules\Subscription\States\SubscriptionInvoice\SubscriptionInvoiceState;
use App\Modules\Subscription\States\SubscriptionInvoice\Unpaid;
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
use Spatie\ModelStates\HasStates;

/**
 * What a tenant owes the platform for one subscription period (FR-SUB-03).
 * Like any financial document it is never edited; a wrong one is voided.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $subscription_id
 * @property string $plan_id
 * @property string $number
 * @property BillingCycle $billing_cycle
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property int $amount
 * @property SubscriptionInvoiceState $status
 * @property Carbon $due_date
 * @property Carbon|null $paid_at
 * @property string|null $gateway_reference
 * @property string|null $payment_note
 * @property string|null $confirmed_by
 * @property Carbon|null $voided_at
 */
#[Fillable(['subscription_id', 'plan_id', 'number', 'billing_cycle', 'period_start', 'period_end', 'amount', 'due_date'])]
#[UseFactory(SubscriptionInvoiceFactory::class)]
class SubscriptionInvoice extends Model
{
    /** @use HasFactory<SubscriptionInvoiceFactory> */
    use Auditable, BelongsToTenant, EnforcesStateTransitions, HasFactory, HasStates, HasUlids;

    public function isUnpaid(): bool
    {
        return $this->status->equals(Unpaid::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionInvoiceState::class,
            'billing_cycle' => BillingCycle::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'amount' => RupiahCast::class,
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
