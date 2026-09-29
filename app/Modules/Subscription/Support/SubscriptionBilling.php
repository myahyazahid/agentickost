<?php

namespace App\Modules\Subscription\Support;

use App\Modules\Subscription\Enums\BillingCycle;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionInvoice;
use App\Modules\Subscription\States\Subscription\Active;
use App\Modules\Subscription\States\SubscriptionInvoice\Paid;
use App\Modules\Subscription\States\SubscriptionInvoice\Unpaid;
use App\Modules\Subscription\States\SubscriptionInvoice\Voided;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\States\StateTransition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Subscription invoices and what paying one does (FR-SUB-03). Numbers are
 * unique across the platform: AK/LGN/{year}/{month}/{sequence}. Call inside
 * the Action's transaction and the tenant's context.
 */
final class SubscriptionBilling
{
    public function issue(Subscription $subscription, Plan $plan, BillingCycle $cycle, CarbonImmutable $periodStart, CarbonImmutable $dueDate): SubscriptionInvoice
    {
        $amount = $plan->priceFor($cycle) ?? throw ValidationException::withMessages([
            'billing_cycle' => "Paket {$plan->name} tidak dijual ".mb_strtolower($cycle->getLabel()).'.',
        ]);

        return SubscriptionInvoice::create([
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'number' => self::nextNumber($dueDate),
            'billing_cycle' => $cycle,
            'period_start' => $periodStart,
            'period_end' => $periodStart->addMonthsNoOverflow($cycle->months())->subDay(),
            'amount' => $amount,
            'due_date' => $dueDate,
        ]);
    }

    /**
     * Unpaid invoices of the subscription are withdrawn, for example when
     * the owner picks another plan.
     */
    public function voidUnpaid(Subscription $subscription): void
    {
        $subscription->invoices()->where('status', Unpaid::$name)->get()->each(function (SubscriptionInvoice $invoice): void {
            $invoice->voided_at = now();
            StateTransition::to($invoice->status, Voided::class);
        });
    }

    /**
     * A paid invoice makes the subscription active on its plan for its
     * period, whatever stage the subscription had reached.
     */
    public function settle(SubscriptionInvoice $invoice, CarbonImmutable $paidAt, ?string $note, ?string $confirmedBy): Subscription
    {
        $invoice->paid_at = Carbon::parse($paidAt);
        $invoice->payment_note = $note;
        $invoice->confirmed_by = $confirmedBy;
        StateTransition::to($invoice->status, Paid::class);

        $subscription = $invoice->subscription()->firstOrFail();
        $subscription->plan_id = $invoice->plan_id;
        $subscription->billing_cycle = $invoice->billing_cycle;
        $subscription->current_period_start = $subscription->current_period_start === null || ! $subscription->status->equals(Active::class)
            ? $invoice->period_start
            : $subscription->current_period_start;
        $subscription->current_period_end = $invoice->period_end;
        $subscription->grace_ends_at = null;
        $subscription->read_only_since = null;
        $subscription->cancelled_at = null;

        if ($subscription->status->equals(Active::class)) {
            $subscription->save();
        } else {
            StateTransition::to($subscription->status, Active::class);
        }

        return $subscription;
    }

    public static function trialEndsOn(Tenant $tenant): ?CarbonImmutable
    {
        return $tenant->trial_ends_at === null
            ? null
            : CarbonImmutable::parse($tenant->trial_ends_at->timezone($tenant->default_timezone)->toDateString());
    }

    public static function todayFor(Tenant $tenant): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now($tenant->default_timezone)->toDateString());
    }

    private static function nextNumber(CarbonImmutable $date): string
    {
        $prefix = 'AK/LGN/'.$date->format('Y/m').'/';

        $last = SubscriptionInvoice::query()
            ->withoutGlobalScopes()
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->max('number');

        $sequence = is_string($last) ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
