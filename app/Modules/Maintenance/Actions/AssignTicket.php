<?php

namespace App\Modules\Maintenance\Actions;

use App\Modules\Access\Models\User;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\States\Ticket\Assigned;
use App\Modules\Maintenance\States\Ticket\InProgress;
use App\Modules\Maintenance\States\Ticket\Reported;
use App\Modules\Maintenance\Support\TicketLog;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Gives a ticket to a staff member of the property (FR-MNT-02). A ticket
 * already assigned or in progress can be handed to someone else. Vendors
 * arrive in M2.2.
 */
final class AssignTicket extends Action
{
    public function __construct(private readonly TicketLog $log) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Ticket $ticket, array $input): Ticket
    {
        $this->authorize('manage', $ticket);

        $data = $this->validate($input, [
            'assigned_user_id' => ['required', 'string'],
        ]);

        $staff = User::query()->whereKey($data['assigned_user_id'])->where('is_active', true)->first();
        $property = $ticket->property()->firstOrFail();

        if ($staff === null || ! $property->isAccessibleBy($staff)) {
            throw ValidationException::withMessages(['assigned_user_id' => 'Pilih staf aktif yang bertugas di properti ini.']);
        }

        return $this->transaction(function () use ($ticket, $staff): Ticket {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (! $ticket->status->equals(Reported::class, Assigned::class, InProgress::class)) {
                throw ValidationException::withMessages(['assigned_user_id' => 'Tiket ini sudah tidak bisa ditugaskan.']);
            }

            $ticket->assigned_user_id = $staff->id;

            if ($ticket->status->equals(Reported::class)) {
                $this->log->move($ticket, Assigned::class, "Ditugaskan ke {$staff->name}", 'assigned_user_id');
            } else {
                $ticket->save();
                $this->log->note($ticket, "Dialihkan ke {$staff->name}");
            }

            return $ticket;
        });
    }
}
