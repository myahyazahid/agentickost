<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\States\Subscription\Active;
use App\Modules\Subscription\States\Subscription\Cancelled;
use App\Modules\Subscription\Support\CurrentSubscription;
use App\Modules\Subscription\Support\SubscriptionBilling;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\States\StateTransition;
use Illuminate\Validation\ValidationException;

/**
 * Takes back a cancellation while the paid period still runs.
 */
final class ResumeSubscription extends Action
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function handle(): Subscription
    {
        $subscription = CurrentSubscription::get();
        $this->authorize('manage', $subscription);

        $today = SubscriptionBilling::todayFor($this->tenants->tenant());

        if (! $subscription->status->equals(Cancelled::class) || $subscription->current_period_end === null || $subscription->current_period_end->lessThan($today)) {
            throw ValidationException::withMessages(['status' => 'Langganan ini tidak bisa dilanjutkan. Pilih paket untuk berlangganan lagi.']);
        }

        return $this->transaction(function () use ($subscription): Subscription {
            $subscription->cancelled_at = null;
            StateTransition::to($subscription->status, Active::class);

            return $subscription;
        });
    }
}
