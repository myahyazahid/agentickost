<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Property\Models\Room;

/**
 * What the current occupant of a room owes past the due date, for the room
 * grid (FR-KMR-05).
 */
final class Arrears
{
    public static function forRoom(Room $room): int
    {
        $property = $room->relationLoaded('property') ? $room->getRelation('property') : $room->property()->first();

        if ($property === null) {
            return 0;
        }

        return (int) Invoice::query()
            ->whereIn('status', InvoiceState::openValues())
            ->whereDate('due_date', '<', $property->today())
            ->whereIn('contract_id', Contract::query()
                ->where('room_id', $room->id)
                ->whereIn('status', ContractState::runningValues())
                ->select('id'))
            ->sum('balance_amount');
    }
}
