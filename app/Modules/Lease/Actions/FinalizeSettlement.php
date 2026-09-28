<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Billing\Support\FinalBilling;
use App\Modules\Finance\Actions\RefundDeposit;
use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Finance\Events\DepositRefunded;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Enums\ItemCondition;
use App\Modules\Lease\Enums\RoomAfterCheckOut;
use App\Modules\Lease\Events\ContractCompleted;
use App\Modules\Lease\Events\SettlementFinalized;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\InspectionItem;
use App\Modules\Lease\Models\Settlement;
use App\Modules\Lease\States\Contract\Completed;
use App\Modules\Lease\States\Contract\Terminated;
use App\Modules\Lease\States\Settlement\Finalized;
use App\Modules\Lease\Support\SettlementCalculator;
use App\Modules\Payment\Support\CreditLedger;
use App\Modules\Payment\Support\DepositPayments;
use App\Modules\Property\Support\RoomOccupancy;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use App\Support\Money\Rupiah;
use App\Support\States\StateTransition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Settles a check-out (FR-SIK-05, PRD §8.9), in one transaction:
 *
 * 1. Rent is billed to the last day of the stay; deposit billed but never
 *    paid is dropped.
 * 2. A final invoice charges the damage, the penalty, and the last meter
 *    readings.
 * 3. Credit balance, then deposit, pay the open invoices, oldest first.
 * 4. Whatever deposit and credit is left is paid back from the chosen
 *    account; whatever is still owed stays on the open invoices.
 * 5. The contract ends, the residents leave, and the room is freed.
 *
 * The recorded numbers follow arrears + damage + penalty − deposit − credit:
 * positive is still owed, negative was paid back.
 */
final class FinalizeSettlement extends Action
{
    public function __construct(
        private readonly FinalBilling $billing,
        private readonly CreditLedger $credit,
        private readonly DepositPayments $depositPayments,
        private readonly DepositLedger $deposits,
        private readonly SettlementCalculator $calculator,
        private readonly RoomOccupancy $rooms,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Settlement $settlement, array $input = []): Settlement
    {
        $contract = $settlement->contract()->firstOrFail();

        $this->authorize('finalizeSettlement', $contract);

        $data = $this->validate($input, [
            'early_termination_amount' => ['nullable', 'integer', 'min:0'],
            'refund_account_id' => ['nullable', 'string'],
        ]);

        if (! $settlement->isDraft()) {
            throw ValidationException::withMessages(['refund_account_id' => 'Penyelesaian ini sudah final.']);
        }

        $refundAccount = ($data['refund_account_id'] ?? null) === null
            ? null
            : RefundDeposit::payoutAccounts()->whereKey($data['refund_account_id'])->first()
                ?? throw ValidationException::withMessages(['refund_account_id' => 'Pilih kas atau rekening asal pengembalian.']);

        return $this->transaction(function () use ($settlement, $contract, $data, $refundAccount): Settlement {
            $contract = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $settlement = Settlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            $property = $contract->property()->firstOrFail();
            $today = $property->today();
            $movedOutOn = CarbonImmutable::parse($settlement->moved_out_on->toDateString());
            $lastDay = $this->calculator->lastDayOfStay($contract) ?? $movedOutOn;
            $penalty = (int) ($data['early_termination_amount'] ?? $settlement->early_termination_amount);

            // Issuing invoices uses credit balance at once, so it is read first.
            $creditHeld = CreditLedger::balance($contract->id);

            $this->billing->billRemainingRent($contract, $lastDay, $today);
            $this->billing->dropUnpaidDeposit($contract);

            $damages = self::damages($settlement);
            $damage = array_sum(array_column($damages, 'amount'));
            $finalInvoice = $this->billing->finalInvoice($contract, $damages, $penalty, $movedOutOn, $today);

            $creditUsedByBilling = $creditHeld - CreditLedger::balance($contract->id);
            $outstanding = FinalBilling::owed($contract) + $creditUsedByBilling - $damage - $penalty;
            $depositHeld = DepositLedger::balance($contract->id);

            $this->credit->applyToOpenInvoices($contract);
            $this->depositPayments->payOpenInvoices($contract, 'Penyelesaian check-out', ['settlement_id' => $settlement->id]);
            $this->refundLeftovers($contract, $settlement, $refundAccount?->id);

            $settlement->outstanding_amount = $outstanding;
            $settlement->damage_amount = $damage;
            $settlement->early_termination_amount = $penalty;
            $settlement->deposit_balance_amount = $depositHeld;
            $settlement->credit_balance_amount = $creditHeld;
            $settlement->result_amount = SettlementCalculator::result($outstanding, $damage, $penalty, $depositHeld, $creditHeld);
            $settlement->final_invoice_id = $finalInvoice?->id;
            $settlement->refund_account_id = $refundAccount?->id;
            $settlement->finalized_by = $this->actors->current()->type === ActorType::User ? $this->actors->current()->id : null;
            $settlement->finalized_at = now();
            StateTransition::to($settlement->status, Finalized::class, 'refund_account_id');

            $this->endStay($contract, $settlement, $lastDay, $movedOutOn);

            SettlementFinalized::dispatch($settlement);

            return $settlement;
        });
    }

    /**
     * @return list<array{description: string, amount: int, source: InspectionItem}>
     */
    private static function damages(Settlement $settlement): array
    {
        $damages = [];

        foreach ($settlement->inspection()->firstOrFail()->items()->where('charge_amount', '>', 0)->get() as $item) {
            $what = $item->condition === ItemCondition::Missing ? 'hilang' : 'rusak';
            $damages[] = [
                'description' => "Ganti rugi {$item->item_name} ({$what})".($item->notes !== null ? ": {$item->notes}" : ''),
                'amount' => $item->charge_amount,
                'source' => $item,
            ];
        }

        return $damages;
    }

    private function refundLeftovers(Contract $contract, Settlement $settlement, ?string $accountId): void
    {
        $deposit = DepositLedger::balance($contract->id);
        $credit = CreditLedger::balance($contract->id);

        if ($deposit + $credit <= 0) {
            return;
        }

        if ($accountId === null) {
            throw ValidationException::withMessages([
                'refund_account_id' => 'Ada '.Rupiah::format($deposit + $credit).' yang harus dikembalikan. Pilih kas atau rekening asalnya.',
            ]);
        }

        if ($deposit > 0) {
            $entry = $this->deposits->record($contract, DepositTransactionType::Refunded, -$deposit, [
                'account_id' => $accountId,
                'settlement_id' => $settlement->id,
                'reason' => 'Sisa deposit saat check-out',
            ]);

            DepositRefunded::dispatch($entry);
        }

        if ($credit > 0) {
            $this->credit->refund($contract, $credit, $accountId);
        }
    }

    private function endStay(Contract $contract, Settlement $settlement, CarbonImmutable $lastDay, CarbonImmutable $movedOutOn): void
    {
        if (! $contract->status->equals(Terminated::class)) {
            $contract->ended_on = Carbon::parse($lastDay->toDateString());
            StateTransition::to($contract->status, Completed::class, 'refund_account_id');
            ContractCompleted::dispatch($contract);
        }

        $contract->occupants()->whereNull('left_on')->get()->each(function ($occupant) use ($movedOutOn): void {
            $occupant->left_on = Carbon::parse($movedOutOn->toDateString());
            $occupant->save();
        });

        $room = $this->rooms->lock($contract->room()->firstOrFail());
        $this->rooms->release($room, $settlement->room_after === RoomAfterCheckOut::Maintenance);
    }
}
