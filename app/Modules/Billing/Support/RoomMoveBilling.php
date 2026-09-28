<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Engine\Proration;
use App\Modules\Billing\Enums\InvoiceItemType;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Billing\States\Invoice\Voided;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\RoomMove;
use App\Modules\Property\Enums\AllocationCategory;
use App\Modules\Property\Models\Room;
use Carbon\CarbonImmutable;

/**
 * The billing side of a room move (PRD §8.8):
 *
 * 1. Every period starting by the move date is billed first, at the old
 *    room and rent, so the move always lands on an issued invoice.
 * 2. On each rent invoice running past the move, the old room's rent from
 *    the move date on is credited. If it was paid, that money becomes
 *    credit balance (the Payment module releases it).
 * 3. One invoice bills the new room for those same days at the new rent,
 *    any extra deposit, and the old room's last meter readings.
 *
 * Called by the Lease move action inside its transaction, before the
 * contract switches to the new room.
 */
final class RoomMoveBilling
{
    public function __construct(
        private readonly RentInvoiceGenerator $generator,
        private readonly CreditNotes $creditNotes,
        private readonly InvoiceIssuer $issuer,
        private readonly UtilityCharges $utilities,
    ) {}

    public function bill(Contract $contract, RoomMove $move, Room $newRoom, int $extraDeposit, CarbonImmutable $today): ?Invoice
    {
        $movedOn = CarbonImmutable::parse($move->moved_on->toDateString());

        foreach ($this->generator->periodsStartingBy($contract, $movedOn) as $period) {
            $this->generator->issue($contract, $period, $today);
        }

        $rentLines = [];

        foreach ($this->invoicesRunningPast($contract, $movedOn) as $invoice) {
            $periodStart = CarbonImmutable::parse((string) $invoice->period_start?->toDateString());
            $periodEnd = CarbonImmutable::parse((string) $invoice->period_end?->toDateString());
            $from = $periodStart->greaterThan($movedOn) ? $periodStart : $movedOn;
            $days = (int) $from->diffInDays($periodEnd) + 1;
            $basis = $this->generator->periodAt($contract, $periodStart)->basisDays ?? ((int) $periodStart->diffInDays($periodEnd) + 1);
            $oldRent = $invoice->items()->where('type', InvoiceItemType::Rent->value)->value('unit_amount');

            $unused = min(
                Proration::amount((int) $oldRent, $days, $basis),
                CreditNotes::creditable($invoice, AllocationCategory::Rent),
            );

            if ($unused > 0) {
                $this->creditNotes->issue(
                    $invoice,
                    AllocationCategory::Rent,
                    $unused,
                    sprintf('Pindah ke kamar %s pada %s: sewa kamar lama %s sampai %s dikembalikan', $newRoom->number, $movedOn->translatedFormat('j M Y'), $from->translatedFormat('j M Y'), $periodEnd->translatedFormat('j M Y')),
                    ['room_move_id' => $move->id],
                );
            }

            $rentLines[] = [
                'from' => $from,
                'to' => $periodEnd,
                'amount' => Proration::amount($move->new_rent_amount, $days, $basis),
                'days' => $days,
                'basis' => $basis,
            ];
        }

        $invoice = Invoice::create([
            'property_id' => $contract->property_id,
            'contract_id' => $contract->id,
            'payer_id' => $contract->payer_id,
            'type' => InvoiceType::Adhoc,
            'generation_key' => "move:{$move->id}",
            'period_start' => $movedOn,
            'period_end' => $rentLines === [] ? $movedOn : end($rentLines)['to'],
            'due_date' => $today,
        ]);

        $sortOrder = 0;

        foreach ($rentLines as $line) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'type' => InvoiceItemType::Rent,
                'allocation_category' => InvoiceItemType::Rent->allocationCategory(),
                'description' => sprintf(
                    'Sewa kamar %s, %s sampai %s%s',
                    $newRoom->number,
                    $line['from']->translatedFormat('j M Y'),
                    $line['to']->translatedFormat('j M Y'),
                    $line['days'] < $line['basis'] ? " (prorata {$line['days']}/{$line['basis']} hari)" : '',
                ),
                'quantity' => 1,
                'unit_amount' => $move->new_rent_amount,
                'amount' => $line['amount'],
                'period_start' => $line['from'],
                'period_end' => $line['to'],
                'sort_order' => $sortOrder++,
            ]);
        }

        if ($extraDeposit > 0) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'type' => InvoiceItemType::Deposit,
                'allocation_category' => InvoiceItemType::Deposit->allocationCategory(),
                'description' => "Tambahan deposit kamar {$newRoom->number}",
                'quantity' => 1,
                'unit_amount' => $extraDeposit,
                'amount' => $extraDeposit,
                'sort_order' => $sortOrder++,
            ]);
        }

        $this->utilities->addReadingsTo($invoice, $contract, $movedOn, $sortOrder);

        if (! $invoice->items()->exists()) {
            $invoice->delete();

            return null;
        }

        return $this->issuer->issue($invoice, $today);
    }

    /**
     * Rent invoices whose period runs past the move date, locked.
     *
     * @return list<Invoice>
     */
    private function invoicesRunningPast(Contract $contract, CarbonImmutable $movedOn): array
    {
        return array_values(Invoice::query()
            ->where('contract_id', $contract->id)
            ->where('type', InvoiceType::Rent->value)
            ->whereNot('status', Voided::$name)
            ->whereDate('period_end', '>=', $movedOn)
            ->orderBy('period_start')
            ->lockForUpdate()
            ->get()
            ->all());
    }
}
