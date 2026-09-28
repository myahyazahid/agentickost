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
use App\Modules\Lease\Models\RoomMove;
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
        return $this->write($invoice, $this->lines($contract, $period, $upTo), $sortOrder);
    }

    /**
     * Only the unbilled readings of the contract's current room, such as the
     * last readings of a room being left.
     *
     * @return int the next sort order
     */
    public function addReadingsTo(Invoice $invoice, Contract $contract, CarbonImmutable $upTo, int $sortOrder): int
    {
        return $this->write($invoice, $this->readingLines($contract, $upTo), $sortOrder);
    }

    /**
     * @param  list<UtilityLine>  $lines
     */
    private function write(Invoice $invoice, array $lines, int $sortOrder): int
    {
        foreach ($lines as $line) {
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
        $lines = $withReadings ? $this->readingLines($contract, $upTo) : [];

        if (! $contract->rental_period->isMonthBased()) {
            return $lines;
        }

        return [...$lines, ...$this->flatFeeLines($contract, $period)];
    }

    /**
     * Lines for the unbilled readings of the contract's current room.
     *
     * @return list<UtilityLine>
     */
    public function readingLines(Contract $contract, CarbonImmutable $upTo): array
    {
        $lines = [];

        foreach ($this->unbilledReadings($contract, $upTo) as $reading) {
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

        return $lines;
    }

    /**
     * @return list<UtilityLine>
     */
    private function flatFeeLines(Contract $contract, BillingPeriod $period): array
    {
        $lines = [];

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
     * Readings taken after the resident moved into the room, so a reading on
     * move-in day (the previous resident's last one) is never billed to them.
     * Renewals count from the first contract of the chain; after a room move
     * the new room counts from the move date (PRD �8.8).
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
        $chain = [$contract->id];

        while ($first->renewed_from_contract_id !== null && ($previous = $first->renewedFrom()->first()) !== null) {
            $first = $previous;
            $chain[] = $first->id;
        }

        $movedIn = RoomMove::query()
            ->whereIn('contract_id', $chain)
            ->where('to_room_id', $contract->room_id)
            ->max('moved_on');

        $start = $first->start_date->toImmutable();

        return is_string($movedIn) && CarbonImmutable::parse($movedIn)->greaterThan($start) ? CarbonImmutable::parse($movedIn) : $start;
    }

    private static function number(string $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
