<?php

namespace App\Modules\Maintenance\Support;

use App\Modules\Billing\Support\DamageCharges;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Support\Expenses;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Maintenance\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Turns a confirmed ticket's cost into an expense (FR-MNT-04) and, when the
 * resident caused the damage, into a charge on their contract (FR-MNT-05).
 * Call inside the confirming Action's transaction.
 */
final class TicketCharges
{
    public function __construct(
        private readonly Expenses $expenses,
        private readonly DamageCharges $charges,
    ) {}

    public function book(Ticket $ticket): void
    {
        if ($ticket->cost_amount === 0 || $ticket->paid_from_account_id === null) {
            return;
        }

        $property = $ticket->property()->firstOrFail();
        $today = $property->today();
        $resolvedOn = $ticket->resolved_at === null
            ? $today
            : CarbonImmutable::parse($ticket->resolved_at->timezone($property->timezone->value)->toDateString());

        $expense = $this->expenses->record(
            $property,
            Account::system(AccountSubtype::MaintenanceExpense)->id,
            $ticket->paid_from_account_id,
            $ticket->cost_amount,
            $resolvedOn,
            "Perbaikan: {$ticket->title} ({$ticket->location()})",
            ['ticket_id' => $ticket->id],
        );
        $ticket->expense_id = $expense->id;

        if ($ticket->charge_to_resident) {
            $contract = self::residentContract($ticket)
                ?? throw ValidationException::withMessages(['charge_to_resident' => 'Tidak ada kontrak berjalan di kamar ini untuk ditagih.']);

            $ticket->charge_invoice_id = $this->charges->charge(
                $contract,
                "Biaya perbaikan: {$ticket->title}",
                $ticket->cost_amount,
                $ticket,
                $today,
            )->id;
        }

        $ticket->save();
    }

    /**
     * The running contract of the ticket's room, whose payer is billed.
     */
    public static function residentContract(Ticket $ticket): ?Contract
    {
        if ($ticket->room_id === null) {
            return null;
        }

        return Contract::query()
            ->where('room_id', $ticket->room_id)
            ->whereIn('status', ContractState::runningValues())
            ->first();
    }
}
