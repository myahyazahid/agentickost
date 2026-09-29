<?php

namespace App\Providers;

use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use App\Support\Backup\Commands\BackupBinaryLogs;
use App\Support\Backup\Commands\BackupDatabase;
use App\Support\Backup\Commands\TestRestore;
use App\Support\Backup\MysqlClient;
use App\Support\Messaging\LogMessageChannel;
use App\Support\Messaging\MessageChannel;
use App\Support\Modules\ModuleRegistry;
use Filament\Forms\Components\DatePicker;
use Illuminate\Log\Context\Repository;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

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

        $this->app->singleton(MysqlClient::class, fn (): MysqlClient => MysqlClient::fromConfig());

        $this->app->singleton(MessageChannel::class, fn (): MessageChannel => match (config('agentickost.whatsapp.driver')) {
            'log' => new LogMessageChannel,
            default => throw new InvalidArgumentException('AGENTICKOST_WHATSAPP_DRIVER tidak dikenal. Pilihan yang tersedia: log.'),
        });
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

        // Staging and production refuse weak and leaked passwords; local
        // development and tests keep the plain minimum.
        Password::defaults(fn (): Password => $this->app->environment('local', 'testing')
            ? Password::min(8)
            : Password::min(8)->letters()->numbers()->uncompromised());

        if ($this->app->runningInConsole()) {
            $this->commands([BackupDatabase::class, BackupBinaryLogs::class, TestRestore::class]);
        }

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
