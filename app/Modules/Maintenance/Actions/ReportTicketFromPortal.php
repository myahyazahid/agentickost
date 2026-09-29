<?php

namespace App\Modules\Maintenance\Actions;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Models\Contract;
use App\Modules\Maintenance\Enums\MaintenancePermission;
use App\Modules\Maintenance\Enums\TicketCategory;
use App\Modules\Maintenance\Enums\TicketPriority;
use App\Modules\Maintenance\Filament\App\Resources\Tickets\TicketResource;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\Support\TicketIntake;
use App\Modules\Property\Models\Property;
use App\Support\Actions\Action;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

/**
 * A resident reports a problem in their room or in a common area of the
 * property they live in, from the portal (FR-PRT-04). Staff set the
 * priority when they assign it; it starts as normal. Those who manage
 * tickets at the property are told.
 */
final class ReportTicketFromPortal extends Action
{
    public function __construct(private readonly TicketIntake $intake) {}

    /**
     * @param  array<string, mixed>  $input  category, title, description, in_room, photos
     */
    public function handle(Contract $contract, array $input): Ticket
    {
        $this->authorize('reportFromPortal', [Ticket::class, $contract]);

        $data = $this->validate($input, [
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'in_room' => ['required', 'boolean'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['string'],
        ]);

        $property = $contract->property()->firstOrFail();

        $ticket = $this->transaction(fn (): Ticket => $this->intake->open(
            $property,
            $data['in_room'] ? $contract->room_id : null,
            [...$data, 'priority' => TicketPriority::Normal->value],
            array_values($data['photos'] ?? []),
        ));

        $this->tellManagers($ticket, $property, $contract);

        return $ticket;
    }

    private function tellManagers(Ticket $ticket, Property $property, Contract $contract): void
    {
        $managers = User::query()
            ->permission(MaintenancePermission::ManageTickets->value)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => $property->isAccessibleBy($user));

        Notification::make()
            ->warning()
            // Notifications render limited HTML; text typed by residents is escaped.
            ->title(e("Laporan penghuni: {$ticket->title}"))
            ->body(e(($ticket->room_id !== null ? "Kamar {$contract->room?->number}" : 'Area umum').", {$property->name}."))
            ->actions([
                NotificationAction::make('open')
                    ->label('Buka tiket')
                    ->url(TicketResource::getUrl('view', ['record' => $ticket], panel: 'app')),
            ])
            ->sendToDatabase($managers);
    }
}
