<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Lease\States\Contract\Notice;
use App\Modules\Property\Support\RoomOccupancy;
use App\Support\Actions\Action;
use App\Support\States\StateTransition;
use Illuminate\Validation\ValidationException;

/**
 * The resident decided to stay after all.
 */
final class CancelNotice extends Action
{
    public function __construct(private readonly RoomOccupancy $rooms) {}

    public function handle(Contract $contract): Contract
    {
        $this->authorize('update', $contract);

        if (! $contract->status->equals(Notice::class)) {
            throw ValidationException::withMessages(['status' => 'Kontrak ini tidak sedang dalam pemberitahuan keluar.']);
        }

        return $this->transaction(function () use ($contract): Contract {
            $room = $this->rooms->lock($contract->room()->firstOrFail());

            $contract->notice_given_on = null;
            $contract->planned_move_out_on = null;
            StateTransition::to($contract->status, Active::class);

            $this->rooms->cancelVacating($room);

            return $contract;
        });
    }
}
