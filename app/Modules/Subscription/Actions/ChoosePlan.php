<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Subscription\Enums\BillingCycle;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionInvoice;
use App\Modules\Subscription\States\Subscription\Active;
use App\Modules\Subscription\States\Subscription\Cancelled;
use App\Modules\Subscription\States\Subscription\Trial;
use App\Modules\Subscription\Support\CurrentSubscription;
use App\Modules\Subscription\Support\SubscriptionBilling;
use App\Modules\Subscription\Support\SubscriptionUsage;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\Subscriptions\AllowedWhenReadOnly;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The owner picks a plan, or moves to another one (FR-SUB-05). Moving to a
 * plan whose limits the tenant already exceeds is refused.
 *
 * - With a paid period running, the new plan and its limits apply at once;
 *   the next renewal invoice is at the new price. There is no proration.
 * - Otherwise (trial, or a subscription not paid), an invoice is issued: in
 *   a trial for the period right after it, due on its last day; after that,
 *   from today and due today.
 */
final class ChoosePlan extends Action implements AllowedWhenReadOnly
{
    public function __construct(
        private readonly SubscriptionBilling $billing,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  array<string, mixed>  $input  plan_id, billing_cycle
     */
    public function handle(array $input): ?SubscriptionInvoice
    {
        $subscription = CurrentSubscription::get();
        $this->authorize('manage', $subscription);

        $data = $this->validate($input, [
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('is_active', true)],
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
        ]);

        $plan = Plan::query()->whereKey($data['plan_id'])->firstOrFail();
        $cycle = BillingCycle::from($data['billing_cycle']);

        if ($plan->priceFor($cycle) === null) {
            throw ValidationException::withMessages(['billing_cycle' => "Paket {$plan->name} tidak dijual ".mb_strtolower($cycle->getLabel()).'.']);
        }

        $exceeding = SubscriptionUsage::exceeding($plan);

        if ($exceeding !== []) {
            throw ValidationException::withMessages([
                'plan_id' => 'Pemakaian sekarang melebihi batas paket ini: '.implode('; ', $exceeding).'. Kurangi dulu, atau pilih paket yang lebih besar.',
            ]);
        }

        return $this->transaction(function () use ($subscription, $plan, $cycle): ?SubscriptionInvoice {
            $subscription = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $this->billing->voidUnpaid($subscription);

            $subscription->plan_id = $plan->id;
            $subscription->billing_cycle = $cycle;
            $subscription->save();

            if ($subscription->status->equals(Active::class) || $subscription->status->equals(Cancelled::class)) {
                return null;
            }

            $tenant = $this->tenants->tenant();
            $today = SubscriptionBilling::todayFor($tenant);
            $trialEnd = SubscriptionBilling::trialEndsOn($tenant);
            $inTrial = $subscription->status->equals(Trial::class) && $trialEnd !== null && $trialEnd->greaterThanOrEqualTo($today);

            return $this->billing->issue(
                $subscription,
                $plan,
                $cycle,
                $inTrial ? $trialEnd->addDay() : $today,
                $inTrial ? $trialEnd : $today,
            );
        });
    }
}
