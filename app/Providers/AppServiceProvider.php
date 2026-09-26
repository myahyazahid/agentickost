<?php

namespace App\Providers;

use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use App\Support\Modules\ModuleRegistry;
use Filament\Forms\Components\DatePicker;
use Illuminate\Log\Context\Repository;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        foreach (ModuleRegistry::providers() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Same date format on every browser, whatever its language setting.
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker
            ->native(false)
            ->displayFormat('j M Y')
            ->firstDayOfWeek(1));

        Context::hydrated(function (Repository $context): void {
            $actors = $this->app->make(ActorContext::class);
            $actor = $context->getHidden(ActorContext::CONTEXT_KEY);

            if (is_array($actor)) {
                $actors->set(Actor::fromArray($actor));
            } else {
                $actors->forget();
            }
        });
    }
}
