<?php

namespace App\Modules\Maintenance\Support;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Maintenance\Events\TicketReported;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\Models\TicketUpdate;
use App\Modules\Property\Models\Property;
use App\Support\Actors\ActorContext;

/**
 * Opens a ticket with its first log line and photos, for whoever reports
 * it: staff in the panel or a resident in the portal. Call inside the
 * Action's transaction.
 */
final class TicketIntake
{
    public function __construct(
        private readonly AttachmentSync $attachments,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  array<string, mixed>  $details  validated category, priority, title, and description
     * @param  list<string>  $photos
     */
    public function open(Property $property, ?string $roomId, array $details, array $photos): Ticket
    {
        $actor = $this->actors->current();

        $ticket = Ticket::create([
            'property_id' => $property->id,
            'room_id' => $roomId,
            'reported_by_type' => $actor->type,
            'reported_by_id' => $actor->id,
            'category' => $details['category'],
            'priority' => $details['priority'],
            'title' => $details['title'],
            'description' => $details['description'],
        ]);

        TicketUpdate::create([
            'ticket_id' => $ticket->id,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'to_status' => $ticket->status->getValue(),
            'note' => 'Tiket dilaporkan',
        ]);

        $this->attachments->sync($ticket, AttachmentCollection::Before, $photos);

        TicketReported::dispatch($ticket);

        return $ticket;
    }
}
