<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Support\RentInvoiceGenerator;
use App\Modules\Lease\Models\Contract;
use App\Support\Actions\Action;

/**
 * Issues every rent invoice of a contract whose issue date has come
 * (FR-BIL-01). Safe to repeat: a period already billed is skipped.
 */
final class IssueDueInvoices extends Action
{
    public function __construct(private readonly RentInvoiceGenerator $generator) {}

    /**
     * @return list<Invoice>
     */
    public function handle(Contract $contract): array
    {
        $property = $contract->property()->firstOrFail();

        $this->authorize('createIn', [Invoice::class, $property]);

        if (! $contract->isRunning()) {
            return [];
        }

        $today = $property->today();

        return $this->transaction(function () use ($contract, $today): array {
            $locked = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $invoices = [];

            foreach ($this->generator->duePeriods($locked, $today) as $period) {
                $invoices[] = $this->generator->issue($locked, $period, $today);
            }

            return $invoices;
        });
    }
}
