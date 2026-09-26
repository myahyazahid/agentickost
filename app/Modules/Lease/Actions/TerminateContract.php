<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Lease\Events\ContractTerminated;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\Terminated;
use App\Modules\Property\Support\RoomOccupancy;
use App\Support\Actions\Action;
use App\Support\States\StateTransition;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Ends a contract early with a reason (FR-KTR-04). When it ends before its
 * agreed end date, the contract's early-termination penalty applies unless
 * another amount is given. The penalty is charged in the check-out
 * settlement; the room shows as "akan kosong" until then.
 */
final class TerminateContract extends Action
{
    public function __construct(private readonly RoomOccupancy $rooms) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, array $input): Contract
    {
        $this->authorize('update', $contract);

        if (! $contract->isRunning()) {
            throw ValidationException::withMessages(['status' => 'Hanya kontrak yang sedang berjalan yang bisa diputus.']);
        }

        if ($contract->renewal()->exists()) {
            throw ValidationException::withMessages(['status' => 'Kontrak ini sudah punya draf perpanjangan. Hapus draf itu lebih dulu.']);
        }

        $data = $this->validate($input, [
            'ended_on' => ['required', 'date', 'after_or_equal:'.$contract->start_date->toDateString()],
            'termination_reason' => ['required', 'string'],
            'termination_penalty_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $endedOn = Carbon::parse($data['ended_on']);
        $endsEarly = $contract->end_date !== null && $endedOn->lessThan($contract->end_date);

        return $this->transaction(function () use ($contract, $data, $endedOn, $endsEarly): Contract {
            $room = $this->rooms->lock($contract->room()->firstOrFail());

            $contract->ended_on = $endedOn;
            $contract->termination_reason = $data['termination_reason'];
            $contract->termination_penalty_amount = array_key_exists('termination_penalty_amount', $data) && $data['termination_penalty_amount'] !== null
                ? $data['termination_penalty_amount']
                : ($endsEarly ? $contract->early_termination_penalty_amount : null);
            StateTransition::to($contract->status, Terminated::class);

            $this->rooms->markVacating($room);

            ContractTerminated::dispatch($contract);

            return $contract;
        });
    }
}
