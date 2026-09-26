<?php

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Actions\IssueDueInvoices;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Issues rent invoices whose issue date has come, in each property's own
 * time zone (FR-BIL-01). Runs hourly and is safe to repeat.
 */
final class IssueRentInvoices extends Command
{
    protected $signature = 'billing:issue-invoices';

    protected $description = 'Terbitkan tagihan sewa yang sudah sampai tanggal terbitnya';

    public function handle(ActorContext $actors, TenantContext $tenants, IssueDueInvoices $issue): int
    {
        $issued = 0;

        $actors->actingAs(Actor::system(), function () use ($tenants, $issue, &$issued): void {
            $tenants->each(function () use ($issue, &$issued): void {
                Contract::query()
                    ->whereIn('status', ContractState::runningValues())
                    ->orderBy('id')
                    ->each(function (Contract $contract) use ($issue, &$issued): void {
                        try {
                            $issued += count($issue->handle($contract));
                        } catch (ValidationException $exception) {
                            report($exception);
                        }
                    });
            });
        });

        $this->components->info("{$issued} tagihan diterbitkan.");

        return self::SUCCESS;
    }
}
