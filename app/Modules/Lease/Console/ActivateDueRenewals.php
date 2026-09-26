<?php

namespace App\Modules\Lease\Console;

use App\Modules\Lease\Actions\ActivateContract;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\Draft;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Starts renewals whose start date has come, in each property's own time
 * zone. Runs hourly and is safe to repeat.
 */
final class ActivateDueRenewals extends Command
{
    protected $signature = 'contracts:activate-renewals';

    protected $description = 'Aktifkan perpanjangan kontrak yang sudah sampai tanggal mulainya';

    public function handle(ActorContext $actors, TenantContext $tenants, ActivateContract $activate): int
    {
        $activated = 0;

        $actors->actingAs(Actor::system(), function () use ($tenants, $activate, &$activated): void {
            $tenants->each(function () use ($activate, &$activated): void {
                Contract::query()
                    ->where('status', Draft::$name)
                    ->whereNotNull('renewed_from_contract_id')
                    ->with('property')
                    ->get()
                    ->filter(fn (Contract $contract): bool => $contract->start_date->lessThanOrEqualTo($contract->property()->firstOrFail()->today()))
                    ->each(function (Contract $contract) use ($activate, &$activated): void {
                        try {
                            $activate->handle($contract);
                            $activated++;
                        } catch (ValidationException $exception) {
                            report($exception);
                        }
                    });
            });
        });

        $this->components->info("{$activated} perpanjangan diaktifkan.");

        return self::SUCCESS;
    }
}
