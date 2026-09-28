<?php

namespace App\Modules\Maintenance\Actions;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Maintenance\Enums\TicketCategory;
use App\Modules\Maintenance\Enums\TicketPriority;
use App\Modules\Maintenance\Events\TicketReported;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\Models\TicketUpdate;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reports something to repair or clean in a room or a common area, with
 * photos of the problem (FR-MNT-01, FR-MNT-03). Residents report through
 * the portal once it exists (M1.5.3).
 */
final class ReportTicket extends Action
{
    public function __construct(
        private readonly AttachmentSync $attachments,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Property $property, array $input): Ticket
    {
        $this->authorize('reportIn', [Ticket::class, $property]);

        $data = $this->validate($input, [
            'room_id' => ['nullable', 'string'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['required', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['string'],
        ]);

        if (($data['room_id'] ?? null) !== null && ! Room::query()->where('property_id', $property->id)->whereKey($data['room_id'])->exists()) {
            throw ValidationException::withMessages(['room_id' => 'Kamar tidak ditemukan di properti ini.']);
        }

        return $this->transaction(function () use ($property, $data): Ticket {
            $actor = $this->actors->current();

            $ticket = Ticket::create([
                'property_id' => $property->id,
                'room_id' => $data['room_id'] ?? null,
                'reported_by_type' => $actor->type,
                'reported_by_id' => $actor->id,
                'category' => $data['category'],
                'priority' => $data['priority'],
                'title' => $data['title'],
                'description' => $data['description'],
            ]);

            TicketUpdate::create([
                'ticket_id' => $ticket->id,
                'actor_type' => $actor->type,
                'actor_id' => $actor->id,
                'to_status' => $ticket->status->getValue(),
                'note' => 'Tiket dilaporkan',
            ]);

            $this->attachments->sync($ticket, AttachmentCollection::Before, $data['photos'] ?? []);

            TicketReported::dispatch($ticket);

            return $ticket;
        });
    }
}
