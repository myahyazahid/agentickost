<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Enums\InvoiceItemType;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Lease\Models\Contract;
use App\Modules\Property\Enums\AllocationCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The billing side of a check-out (PRD §8.9): rent up to the last day of the
 * stay, unpaid deposit dropped, and a final invoice for damage, penalty, and
 * the last meter readings. Called by the Lease settlement action inside its
 * transaction.
 */
final class FinalBilling
{
    public function __construct(
        private readonly RentInvoiceGenerator $generator,
        private readonly InvoiceIssuer $issuer,
        private readonly UtilityCharges $utilities,
        private readonly CreditNotes $creditNotes,
    ) {}

    /**
     * Bills every period not billed yet that starts by the last day of the
     * stay, even if its issue date has not come.
     */
    public function billRemainingRent(Contract $contract, CarbonImmutable $lastDay, CarbonImmutable $today): void
    {
        foreach ($this->generator->periodsStartingBy($contract, $lastDay) as $period) {
            $this->generator->issue($contract, $period, $today);
        }
    }

    /**
     * Deposit billed but not paid is no longer asked for once the resident
     * leaves; it is credited on its invoice.
     */
    public function dropUnpaidDeposit(Contract $contract): void
    {
        foreach (self::openInvoices($contract) as $invoice) {
            $charged = CreditNotes::creditable($invoice, AllocationCategory::Deposit);
            $paid = (int) $invoice->paymentAllocations()
                ->whereNull('reversed_at')
                ->where('allocation_category', AllocationCategory::Deposit->value)
                ->sum('amount');
            $unpaid = $charged - $paid;

            if ($unpaid > 0) {
                $this->creditNotes->issue($invoice, AllocationCategory::Deposit, $unpaid, 'Deposit tidak ditagih lagi karena penghuni keluar');
            }
        }
    }

    /**
     * @param  list<array{description: string, amount: int, source: Model}>  $damages
     */
    public function finalInvoice(
        Contract $contract,
        array $damages,
        int $penalty,
        CarbonImmutable $movedOutOn,
        CarbonImmutable $today,
    ): ?Invoice {
        $invoice = Invoice::create([
            'property_id' => $contract->property_id,
            'contract_id' => $contract->id,
            'payer_id' => $contract->payer_id,
            'type' => InvoiceType::FinalSettlement,
            'generation_key' => "final:{$contract->id}",
            'due_date' => $today,
        ]);

        $sortOrder = 0;

        foreach ($damages as $damage) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'type' => InvoiceItemType::Damage,
                'allocation_category' => InvoiceItemType::Damage->allocationCategory(),
                'description' => $damage['description'],
                'quantity' => 1,
                'unit_amount' => $damage['amount'],
                'amount' => $damage['amount'],
                'source_type' => $damage['source']->getMorphClass(),
                'source_id' => $damage['source']->getKey(),
                'sort_order' => $sortOrder++,
            ]);
        }

        if ($penalty > 0) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'type' => InvoiceItemType::Other,
                'allocation_category' => InvoiceItemType::Other->allocationCategory(),
                'description' => 'Penalti keluar sebelum waktunya',
                'quantity' => 1,
                'unit_amount' => $penalty,
                'amount' => $penalty,
                'sort_order' => $sortOrder++,
            ]);
        }

        $this->utilities->addReadingsTo($invoice, $contract, $movedOutOn, $sortOrder);

        if (! $invoice->items()->exists()) {
            $invoice->delete();

            return null;
        }

        return $this->issuer->issue($invoice, $today);
    }

    /**
     * The contract's invoices still expecting money, locked.
     *
     * @return list<Invoice>
     */
    public static function openInvoices(Contract $contract): array
    {
        return array_values(Invoice::query()
            ->where('contract_id', $contract->id)
            ->whereIn('status', InvoiceState::openValues())
            ->orderBy('due_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->all());
    }

    public static function owed(Contract $contract): int
    {
        return (int) Invoice::query()
            ->where('contract_id', $contract->id)
            ->whereIn('status', InvoiceState::openValues())
            ->sum('balance_amount');
    }
}
