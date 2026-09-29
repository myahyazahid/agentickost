<?php

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Actions\AccrueInvoicePenalties;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use App\Support\Console\IsolatedRuns;
use Illuminate\Console\Command;

/**
 * Charges late penalties on open invoices past their grace period
 * (PRD §8.4). Runs hourly so each property's midnight is picked up; a day
 * already charged is never charged again. An invoice that fails is
 * reported and skipped.
 */
final class AccruePenalties extends Command
{
    protected $signature = 'billing:accrue-penalties';

    protected $description = 'Hitung denda keterlambatan tagihan yang melewati masa tenggang';

    public function handle(ActorContext $actors, TenantContext $tenants, AccrueInvoicePenalties $accrue): int
    {
        $accrued = 0;
        $runs = new IsolatedRuns;

        $actors->actingAs(Actor::system(), function () use ($tenants, $accrue, $runs, &$accrued): void {
            $tenants->each(function () use ($accrue, $runs, &$accrued): void {
                $runs->attempt(function () use ($accrue, $runs, &$accrued): void {
                    Invoice::query()
                        ->whereIn('status', InvoiceState::openValues())
                        ->where('balance_amount', '>', 0)
                        ->whereDate('due_date', '<=', now()->addDay())
                        ->orderBy('id')
                        ->each(function (Invoice $invoice) use ($accrue, $runs, &$accrued): void {
                            $runs->attempt(function () use ($accrue, $invoice, &$accrued): void {
                                $accrued += count($accrue->handle($invoice));
                            });
                        });
                });
            });
        });

        $this->components->info("{$accrued} denda dicatat.");

        return $runs->finish($this);
    }
}
