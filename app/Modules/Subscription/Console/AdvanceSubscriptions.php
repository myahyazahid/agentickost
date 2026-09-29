<?php

namespace App\Modules\Subscription\Console;

use App\Modules\Access\Models\User;
use App\Modules\Subscription\Actions\AdvanceSubscription;
use App\Modules\Subscription\Enums\SubscriptionEvent;
use App\Modules\Subscription\Enums\SubscriptionPermission;
use App\Modules\Subscription\Filament\App\Pages\SubscriptionPage;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use App\Support\Console\IsolatedRuns;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

/**
 * Moves every tenant's subscription along its lifecycle (PRD §9.6) and
 * tells the owners what changed. Runs hourly and is safe to repeat. Frozen
 * tenants are skipped: only a payment opens them again. Does nothing when
 * subscriptions are not enforced (local development).
 */
final class AdvanceSubscriptions extends Command
{
    protected $signature = 'subscriptions:advance';

    protected $description = 'Perbarui status langganan tenant dan terbitkan tagihan perpanjangan';

    public function handle(ActorContext $actors, TenantContext $tenants, AdvanceSubscription $advance): int
    {
        if (! config('agentickost.subscription_enforced')) {
            $this->components->info('Langganan tidak ditegakkan (AGENTICKOST_SUBSCRIPTION_ENFORCED=false), tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $changed = 0;
        $runs = new IsolatedRuns;

        $actors->actingAs(Actor::system(), function () use ($tenants, $advance, $runs, &$changed): void {
            $tenants->each(function () use ($advance, $runs, &$changed): void {
                $runs->attempt(function () use ($advance, &$changed): void {
                    $events = $advance->handle();

                    foreach ($events as $event) {
                        $this->tell($event);
                    }

                    $changed += $events === [] ? 0 : 1;
                });
            });
        });

        $this->components->info("{$changed} langganan berubah.");

        return $runs->finish($this);
    }

    private function tell(SubscriptionEvent $event): void
    {
        $owners = User::query()
            ->permission(SubscriptionPermission::Manage->value)
            ->where('is_active', true)
            ->get();

        $notification = Notification::make()
            ->title($event->title())
            ->body($event->body())
            ->actions([
                Action::make('open')
                    ->label('Buka Langganan')
                    ->url(SubscriptionPage::getUrl(panel: 'app')),
            ]);

        $event === SubscriptionEvent::RenewalIssued ? $notification->info() : $notification->danger();

        $notification->sendToDatabase($owners);
    }
}
