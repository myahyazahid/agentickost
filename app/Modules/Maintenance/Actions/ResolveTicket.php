<?php

namespace App\Modules\Maintenance\Actions;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Finance\Support\SpendingAccounts;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\States\Ticket\AwaitingConfirmation;
use App\Modules\Maintenance\Support\TicketCharges;
use App\Modules\Maintenance\Support\TicketLog;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use Illuminate\Validation\ValidationException;

/**
 * The repair is done: what was done, photos after, what it cost and which
 * cash or bank paid it, and whether the resident pays for it (FR-MNT-03 to
 * FR-MNT-05). The cost is booked when an owner or manager confirms.
 */
final class ResolveTicket extends Action
{
    public function __construct(
        private readonly TicketLog $log,
        private readonly AttachmentSync $attachments,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Ticket $ticket, array $input): Ticket
    {
        // Who may work on it depends on the current assignee, not the caller's copy.
        $ticket->refresh();

        $this->authorize('work', $ticket);

        $data = $this->validate($input, [
            'note' => ['required', 'string', 'min:3', 'max:1000'],
            'cost_amount' => ['nullable', 'integer', 'min:0'],
            'paid_from_account_id' => ['nullable', 'string'],
            'charge_to_resident' => ['sometimes', 'boolean'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['string'],
        ]);

        $cost = (int) ($data['cost_amount'] ?? 0);
        $chargeResident = (bool) ($data['charge_to_resident'] ?? false);
        $paidFrom = $cost > 0 ? $this->paidFrom($data['paid_from_account_id'] ?? null) : null;

        if ($chargeResident && $cost === 0) {
            throw ValidationException::withMessages(['charge_to_resident' => 'Isi biaya perbaikan untuk ditagihkan ke penghuni.']);
        }

        if ($chargeResident && TicketCharges::residentContract($ticket) === null) {
            throw ValidationException::withMessages(['charge_to_resident' => 'Tidak ada kontrak berjalan di kamar ini untuk ditagih.']);
        }

        return $this->transaction(function () use ($ticket, $data, $cost, $paidFrom, $chargeResident): Ticket {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $ticket->resolved_at = now();
            $ticket->cost_amount = $cost;
            $ticket->paid_from_account_id = $paidFrom;
            $ticket->charge_to_resident = $chargeResident;

            $this->log->move($ticket, AwaitingConfirmation::class, $data['note'], 'note');
            $this->attachments->sync($ticket, AttachmentCollection::After, $data['photos'] ?? []);

            return $ticket;
        });
    }

    /**
     * Owners and managers pay from the cash and bank accounts; others from
     * the cash they hold (FR-ACC-03).
     */
    private function paidFrom(mixed $accountId): string
    {
        $user = User::query()->find($this->actors->current()->id);

        if ($accountId === null || $user === null || ! SpendingAccounts::paidFrom($user)->whereKey($accountId)->exists()) {
            throw ValidationException::withMessages(['paid_from_account_id' => 'Pilih kas atau rekening yang membayar perbaikan ini.']);
        }

        return (string) $accountId;
    }
}
