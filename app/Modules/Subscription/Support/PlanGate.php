<?php

namespace App\Modules\Subscription\Support;

use App\Modules\Subscription\Enums\PlanFeature;
use App\Modules\Tenancy\TenantContext;
use App\Support\Subscriptions\ReadOnlyMode;
use App\Support\Subscriptions\SubscriptionGate;
use Illuminate\Validation\ValidationException;

/**
 * Applies the current tenant's subscription: read-only mode (FR-SUB-04),
 * plan limits (FR-SUB-01), and features (FR-SUB-02). Outside a tenant, and
 * during a trial without a plan, everything is allowed. Only reads: a tenant
 * whose subscription row does not exist yet is on its trial.
 */
final class PlanGate implements SubscriptionGate
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function ensureWritable(): void
    {
        if (! $this->enforced()) {
            return;
        }

        if (CurrentSubscription::find()?->status->isWritable() === false) {
            throw ReadOnlyMode::make();
        }
    }

    public function ensureCanAdd(string $resource, int $count = 1, string $errorKey = 'plan'): void
    {
        if (! $this->enforced()) {
            return;
        }

        $plan = CurrentSubscription::find()?->plan()->first();
        $limit = $plan === null ? null : SubscriptionUsage::limit($plan, $resource);

        if ($plan === null || $limit === null) {
            return;
        }

        if (SubscriptionUsage::of($resource) + $count > $limit) {
            throw ValidationException::withMessages([
                $errorKey => "Paket {$plan->name} dibatasi {$limit} ".SubscriptionUsage::label($resource).'. Naikkan paket di menu Langganan untuk menambah lagi.',
            ]);
        }
    }

    public function allows(string $feature): bool
    {
        if (! $this->enforced()) {
            return true;
        }

        $plan = CurrentSubscription::find()?->plan()->first();
        $case = PlanFeature::tryFrom($feature);

        return $plan === null || ($case !== null && $plan->hasFeature($case));
    }

    /**
     * Only inside a tenant, and unless switched off for local development
     * (config agentickost.subscription_enforced).
     */
    private function enforced(): bool
    {
        return $this->tenants->has() && config('agentickost.subscription_enforced');
    }
}
