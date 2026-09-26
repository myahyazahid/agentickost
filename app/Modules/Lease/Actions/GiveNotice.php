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
 * Records that the resident will move out (FR-SIK-03). The room shows as
 * "akan kosong" until check-out. Short notice is judged at settlement
 * against the property's notice period (PRD §8.9).
 */
final class GiveNotice extends Action
{
    public function __construct(private readonly RoomOccupancy $rooms) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, array $input): Contract
    {
        $this->authorize('update', $contract);

        if (! $contract->status->equals(Active::class)) {
            throw ValidationException::withMessages(['status' => 'Pemberitahuan keluar hanya untuk kontrak aktif.']);
        }

        $today = $contract->property()->firstOrFail()->today()->toDateString();

        $data = $this->validate($input, [
            'notice_given_on' => ['nullable', 'date', 'before_or_equal:'.$today],
            'planned_move_out_on' => ['required', 'date', 'after_or_equal:'.($input['notice_given_on'] ?? $today)],
        ]);

        return $this->transaction(function () use ($contract, $data, $today): Contract {
            $room = $this->rooms->lock($contract->room()->firstOrFail());

            $contract->notice_given_on = $data['notice_given_on'] ?? $today;
            $contract->planned_move_out_on = $data['planned_move_out_on'];
            StateTransition::to($contract->status, Notice::class);

            $this->rooms->markVacating($room);

            return $contract;
        });
    }
}
