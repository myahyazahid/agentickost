<?php

namespace App\Modules\Lease\Console;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Enums\LeasePermission;
use App\Modules\Lease\Filament\App\Resources\Contracts\ContractResource;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use App\Support\Console\IsolatedRuns;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

/**
 * Tells owners and managers that a contract without a renewal ends within
 * the property's notice period (FR-KTR-03). Each contract is reminded once.
 */
final class RemindEndingContracts extends Command
{
    protected $signature = 'contracts:remind-ending';

    protected $description = 'Ingatkan pengelola tentang kontrak yang akan berakhir';

    public function handle(ActorContext $actors, TenantContext $tenants): int
    {
        $reminded = 0;
        $runs = new IsolatedRuns;

        $actors->actingAs(Actor::system(), function () use ($tenants, $runs, &$reminded): void {
            $tenants->each(function () use ($runs, &$reminded): void {
                $runs->attempt(function () use ($runs, &$reminded): void {
                    Contract::query()
                        ->where('status', Active::$name)
                        ->whereNotNull('end_date')
                        ->whereNull('end_reminder_sent_at')
                        ->whereDoesntHave('renewal')
                        ->with(['property', 'room'])
                        ->get()
                        ->each(function (Contract $contract) use ($runs, &$reminded): void {
                            $runs->attempt(function () use ($contract, &$reminded): void {
                                $property = $contract->property()->firstOrFail();
                                $window = max($property->resolvedSettings()->notice_days, 7);

                                if ($contract->end_date === null || $contract->end_date->greaterThan($property->today()->addDays($window))) {
                                    return;
                                }

                                $recipients = User::query()
                                    ->permission(LeasePermission::ManageContracts->value)
                                    ->where('is_active', true)
                                    ->get()
                                    ->filter(fn (User $user): bool => $property->isAccessibleBy($user));

                                Notification::make()
                                    ->warning()
                                    // Notifications render limited HTML; names typed by staff are escaped.
                                    ->title(e("Kontrak kamar {$contract->room?->number} berakhir ".$contract->end_date->translatedFormat('j F Y')))
                                    ->body(e("{$property->name}, {$contract->primaryResident()?->full_name}. Siapkan perpanjangan atau check-out."))
                                    ->actions([
                                        Action::make('open')
                                            ->label('Buka kontrak')
                                            ->url(ContractResource::getUrl('view', ['record' => $contract], panel: 'app')),
                                    ])
                                    ->sendToDatabase($recipients);

                                $contract->end_reminder_sent_at = now();
                                $contract->save();
                                $reminded++;
                            });
                        });
                });
            });
        });

        $this->components->info("{$reminded} kontrak diingatkan.");

        return $runs->finish($this);
    }
}
