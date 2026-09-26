<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Engine\BillingPeriod;
use App\Modules\Billing\Engine\Proration;
use App\Modules\Billing\Enums\InvoiceItemType;
use App\Modules\Billing\Enums\UtilityKind;
use App\Modules\Billing\Enums\UtilityMode;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Billing\Models\MeterReading;
use App\Modules\Billing\Models\UtilityRate;
use App\Modules\Lease\Models\Contract;
use Carbon\CarbonImmutable;

/**
 * Utility lines for a rent invoice (FR-UTL-04): unbilled meter readings of
 * the room taken during the stay, and flat monthly fees. Prepaid token
 * utilities are not billed.
 */
final class UtilityCharges
{
    /**
     * @return int the next sort order
     */
    public function addTo(Invoice $invoice, Contract $contract, BillingPeriod $period, CarbonImmutable $upTo, int $sortOrder): int
    {
        foreach ($this->lines($contract, $period, $upTo) as $line) {
            $item = InvoiceItem::create([
                ...$line->attributes,
                'invoice_id' => $invoice->id,
                'sort_order' => $sortOrder++,
            ]);

            $line->reading?->update(['invoice_item_id' => $item->id]);
        }

        return $sortOrder;
    }

    /**
     * The lines without writing anything, for the invoice and its preview.
     *
     * @return list<UtilityLine>
     */
    public function lines(Contract $contract, BillingPeriod $period, CarbonImmutable $upTo, bool $withReadings = true): array
    {
        $lines = [];

        foreach ($withReadings ? $this->unbilledReadings($contract, $upTo) : [] as $reading) {
            $lines[] = new UtilityLine([
                'type' => InvoiceItemType::Utility,
                'allocation_category' => InvoiceItemType::Utility->allocationCategory(),
                'description' => sprintf(
                    '%s %s: %s ke %s',
                    $reading->utility->getLabel(),
                    $reading->reading_date->translatedFormat('j M Y'),
                    self::number($reading->previous_value),
                    self::number($reading->current_value),
                ),
                'quantity' => $reading->usage,
                'unit_amount' => $reading->rate_amount,
                'amount' => $reading->amount,
                'source_type' => $reading->getMorphClass(),
                'source_id' => $reading->id,
            ], $reading);
        }

        if (! $contract->rental_period->isMonthBased()) {
            return $lines;
        }

        foreach (UtilityKind::cases() as $utility) {
            $rate = UtilityRate::inForce($contract->property_id, $utility, $period->start);

            if ($rate === null || $rate->mode !== UtilityMode::Flat || $rate->rate_amount === 0) {
                continue;
            }

            $amount = Proration::amount($rate->rate_amount * (int) $contract->rental_period->months(), $period->billedDays, $period->basisDays);

            $lines[] = new UtilityLine([
                'type' => InvoiceItemType::Utility,
                'allocation_category' => InvoiceItemType::Utility->allocationCategory(),
                'description' => "{$utility->getLabel()} (tarif tetap)",
                'quantity' => 1,
                'unit_amount' => $amount,
                'amount' => $amount,
                'period_start' => $period->start,
                'period_end' => $period->end,
                'source_type' => $rate->getMorphClass(),
                'source_id' => $rate->id,
            ]);
        }

        return $lines;
    }

    /**
     * Readings taken after the resident moved in, so a reading on move-in day
     * (the previous resident's last one) is never billed to them. Renewals
     * count from the first contract of the chain.
     *
     * @return list<MeterReading>
     */
    public function unbilledReadings(Contract $contract, CarbonImmutable $upTo): array
    {
        return array_values(MeterReading::query()
            ->where('room_id', $contract->room_id)
            ->whereNull('invoice_item_id')
            ->where('amount', '>', 0)
            ->whereDate('reading_date', '>', self::movedInOn($contract))
            ->whereDate('reading_date', '<=', $upTo)
            ->orderBy('reading_date')
            ->get()
            ->all());
    }

    private static function movedInOn(Contract $contract): CarbonImmutable
    {
        $first = $contract;

        while ($first->renewed_from_contract_id !== null && ($previous = $first->renewedFrom()->first()) !== null) {
            $first = $previous;
        }

        return $first->start_date->toImmutable();
    }

    private static function number(string $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
