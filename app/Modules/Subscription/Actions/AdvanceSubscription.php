<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Subscription\Enums\SubscriptionEvent;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\States\Subscription\Active;
use App\Modules\Subscription\States\Subscription\Cancelled;
use App\Modules\Subscription\States\Subscription\Frozen;
use App\Modules\Subscription\States\Subscription\Grace;
use App\Modules\Subscription\States\Subscription\Restricted;
use App\Modules\Subscription\States\Subscription\Trial;
use App\Modules\Subscription\States\SubscriptionInvoice\Voided;
use App\Modules\Subscription\Support\BillingSettings;
use App\Modules\Subscription\Support\CurrentSubscription;
use App\Modules\Subscription\Support\SubscriptionBilling;
use App\Modules\Tenancy\Actions\FreezeTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\States\StateTransition;
use App\Support\Subscriptions\AllowedWhenReadOnly;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Moves the current tenant's subscription along PRD §9.6, in the tenant's
 * time zone. Run daily by the scheduler; safe to repeat.
 *
 * - A renewal invoice is issued RENEWAL_NOTICE_DAYS before the paid period
 *   ends, due on its last day.
 * - A trial or paid period that ends unpaid starts the grace period.
 * - A cancelled subscription becomes read-only when its period ends.
 * - When the grace period runs out the tenant becomes read-only, and after
 *   the read-only period it is frozen.
 */
final class AdvanceSubscription extends Action implements AllowedWhenReadOnly
{
    public const RENEWAL_NOTICE_DAYS = 7;

    public function __construct(
        private readonly SubscriptionBilling $billing,
        private readonly TenantContext $tenants,
        private readonly FreezeTenant $freeze,
    ) {}

    /**
     * @return list<SubscriptionEvent> what changed, oldest first
     */
    public function handle(): array
    {
        $subscription = CurrentSubscription::get();
        $this->authorize('advance', $subscription);

        $tenant = $this->tenants->tenant();

        return $this->transaction(function () use ($subscription, $tenant): array {
            $subscription = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $events = [];

            // A tenant left alone for a while may be several stages behind.
            while (($event = $this->step($subscription, $tenant)) !== null) {
                $events[] = $event;
            }

            return $events;
        });
    }

    private function step(Subscription $subscription, Tenant $tenant): ?SubscriptionEvent
    {
        $today = SubscriptionBilling::todayFor($tenant);

        return match (true) {
            $subscription->status->equals(Trial::class) => $this->endTrial($subscription, $tenant, $today),
            $subscription->status->equals(Active::class) => $this->renewOrLapse($subscription, $tenant, $today),
            $subscription->status->equals(Cancelled::class) => $this->endCancelled($subscription, $today),
            $subscription->status->equals(Grace::class) => $this->endGrace($subscription),
            $subscription->status->equals(Restricted::class) => $this->freeze($subscription, $tenant),
            default => null,
        };
    }

    private function endTrial(Subscription $subscription, Tenant $tenant, CarbonImmutable $today): ?SubscriptionEvent
    {
        $trialEnd = SubscriptionBilling::trialEndsOn($tenant);

        if ($trialEnd === null || $today->lessThanOrEqualTo($trialEnd)) {
            return null;
        }

        return $this->startGrace($subscription, $tenant, $trialEnd);
    }

    private function renewOrLapse(Subscription $subscription, Tenant $tenant, CarbonImmutable $today): ?SubscriptionEvent
    {
        $periodEnd = $subscription->current_period_end === null ? null : CarbonImmutable::parse($subscription->current_period_end->toDateString());

        if ($periodEnd === null) {
            return null;
        }

        if ($today->greaterThan($periodEnd)) {
            return $this->startGrace($subscription, $tenant, $periodEnd);
        }

        $plan = $subscription->plan()->first();
        $nextStart = $periodEnd->addDay();

        if ($plan === null || $subscription->billing_cycle === null || $today->lessThan($periodEnd->subDays(self::RENEWAL_NOTICE_DAYS))) {
            return null;
        }

        $alreadyIssued = $subscription->invoices()
            ->whereDate('period_start', $nextStart)
            ->where('status', '!=', Voided::$name)
            ->exists();

        if ($alreadyIssued) {
            return null;
        }

        $this->billing->issue($subscription, $plan, $subscription->billing_cycle, $nextStart, $periodEnd);

        return SubscriptionEvent::RenewalIssued;
    }

    private function endCancelled(Subscription $subscription, CarbonImmutable $today): ?SubscriptionEvent
    {
        if ($subscription->current_period_end !== null && $today->lessThanOrEqualTo(CarbonImmutable::parse($subscription->current_period_end->toDateString()))) {
            return null;
        }

        return $this->restrict($subscription);
    }

    private function endGrace(Subscription $subscription): ?SubscriptionEvent
    {
        if ($subscription->grace_ends_at !== null && now()->lessThan($subscription->grace_ends_at)) {
            return null;
        }

        return $this->restrict($subscription);
    }

    private function freeze(Subscription $subscription, Tenant $tenant): ?SubscriptionEvent
    {
        $since = $subscription->read_only_since ?? now();

        if (now()->lessThan($since->copy()->addDays(BillingSettings::readOnlyDays()))) {
            return null;
        }

        StateTransition::to($subscription->status, Frozen::class);

        if (! $tenant->refresh()->isFrozen()) {
            $this->freeze->handle($tenant, ['reason' => 'Langganan tidak dibayar sampai batas akhir masa baca saja.']);
        }

        return SubscriptionEvent::Frozen;
    }

    /**
     * The grace period runs for BillingSettings::graceDays() whole days
     * after the last covered day, in the tenant's time zone.
     */
    private function startGrace(Subscription $subscription, Tenant $tenant, CarbonImmutable $lastCoveredDay): SubscriptionEvent
    {
        $end = $lastCoveredDay->addDays(BillingSettings::graceDays() + 1)->toDateString();

        $subscription->grace_ends_at = Carbon::parse($end, $tenant->default_timezone)->utc();
        StateTransition::to($subscription->status, Grace::class);

        return SubscriptionEvent::GraceStarted;
    }

    private function restrict(Subscription $subscription): SubscriptionEvent
    {
        $subscription->read_only_since = now();
        StateTransition::to($subscription->status, Restricted::class);

        return SubscriptionEvent::ReadOnlyStarted;
    }
}
