<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\States\Subscription\Cancelled;
use App\Modules\Subscription\Support\CurrentSubscription;
use App\Modules\Subscription\Support\SubscriptionBilling;
use App\Support\Actions\Action;
use App\Support\States\StateTransition;

/**
 * Stops renewing the subscription (PRD §9.6). The paid period runs out as
 * usual; after it the tenant becomes read-only, so its data can still be
 * seen and exported.
 */
final class CancelSubscription extends Action
{
    public function __construct(private readonly SubscriptionBilling $billing) {}

    public function handle(): Subscription
    {
        $subscription = CurrentSubscription::get();
        $this->authorize('manage', $subscription);

        return $this->transaction(function () use ($subscription): Subscription {
            $subscription->cancelled_at = now();
            StateTransition::to($subscription->status, Cancelled::class);
            $this->billing->voidUnpaid($subscription);

            return $subscription;
        });
    }
}
