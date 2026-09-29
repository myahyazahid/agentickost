<?php

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Enums\TicketCategory;
use App\Modules\Maintenance\Enums\TicketPriority;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\Support\TicketIntake;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Support\Actions\Action;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Staff report something to repair or clean in a room or a common area,
 * with photos of the problem (FR-MNT-01, FR-MNT-03). Residents report from
 * the portal through ReportTicketFromPortal.
 */
final class ReportTicket extends Action
{
    public function __construct(private readonly TicketIntake $intake) {}

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

        return $this->transaction(fn (): Ticket => $this->intake->open(
            $property,
            $data['room_id'] ?? null,
            $data,
            array_values($data['photos'] ?? []),
        ));
    }
}
