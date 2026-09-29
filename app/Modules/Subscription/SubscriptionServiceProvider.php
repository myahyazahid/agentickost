<?php

namespace App\Modules\Subscription;

use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Subscription\Console\AdvanceSubscriptions;
use App\Modules\Subscription\Console\PruneDataExports;
use App\Modules\Subscription\Enums\SubscriptionPermission;
use App\Modules\Subscription\Filament\App\ReadOnlyBanner;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionInvoice;
use App\Modules\Subscription\Models\UsageCounter;
use App\Modules\Subscription\Support\PlanGate;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Subscriptions\SubscriptionGate;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Relations\Relation;

class SubscriptionServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SubscriptionGate::class, PlanGate::class);

        $this->callAfterResolving(
            PermissionRegistry::class,
            fn (PermissionRegistry $registry) => $registry->register(SubscriptionPermission::class),
        );
    }

    public function boot(): void
    {
        parent::boot();

        Relation::enforceMorphMap([
            'plan' => Plan::class,
            'subscription' => Subscription::class,
            'subscription_invoice' => SubscriptionInvoice::class,
            'usage_counter' => UsageCounter::class,
        ]);

        FilamentView::registerRenderHook(PanelsRenderHook::BODY_START, fn (): ?Htmlable => $this->app->make(ReadOnlyBanner::class)->render());

        if ($this->app->runningInConsole()) {
            $this->commands([AdvanceSubscriptions::class, PruneDataExports::class]);
        }
    }
}
