<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Support\DocumentNumbers;
use App\Modules\Lease\Events\ContractActivated;
use App\Modules\Lease\Events\ContractCompleted;
use App\Modules\Lease\Events\ContractRenewed;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Lease\States\Contract\Completed;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Lease\States\Contract\Draft;
use App\Modules\Property\Support\RoomOccupancy;
use App\Support\Actions\Action;
use App\Support\States\StateTransition;
use Illuminate\Validation\ValidationException;

/**
 * Starts a draft contract: it gets its number and the room becomes occupied.
 * A renewal instead takes over from the contract it renews, which completes
 * in the same transaction; the room stays occupied throughout.
 */
final class ActivateContract extends Action
{
    public function __construct(
        private readonly RoomOccupancy $rooms,
        private readonly DocumentNumbers $numbers,
    ) {}

    public function handle(Contract $contract): Contract
    {
        $this->authorize('update', $contract);

        if (! $contract->status->equals(Draft::class)) {
            throw ValidationException::withMessages(['status' => 'Hanya kontrak draf yang bisa diaktifkan.']);
        }

        $property = $contract->property()->firstOrFail();
        $previous = $contract->renewedFrom()->first();

        if ($previous !== null && $contract->start_date->greaterThan($property->today())) {
            throw ValidationException::withMessages([
                'status' => 'Perpanjangan ini aktif otomatis pada '.$contract->start_date->translatedFormat('j F Y').'.',
            ]);
        }

        return $this->transaction(function () use ($contract, $property, $previous): Contract {
            $room = $this->rooms->lock($contract->room()->firstOrFail());

            CreateContract::ensureNotLivingElsewhere(
                $contract->residents()->wherePivotNull('left_on')->get(),
                $previous,
            );

            if ($previous === null) {
                $roomTaken = Contract::query()
                    ->where('room_id', $room->id)
                    ->whereIn('status', ContractState::runningValues())
                    ->exists();

                if ($roomTaken) {
                    throw ValidationException::withMessages(['room_id' => "Kamar {$room->number} masih dipakai kontrak lain."]);
                }

                $this->rooms->occupy($room);
            } else {
                $previous->ended_on = $contract->start_date->copy()->subDay();
                StateTransition::to($previous->status, Completed::class);
            }

            $contract->number = $this->numbers->next(DocumentType::Contract, $contract->start_date, $property->code);
            StateTransition::to($contract->status, Active::class);

            if ($previous !== null) {
                ContractCompleted::dispatch($previous);
                ContractRenewed::dispatch($previous, $contract);
            }

            ContractActivated::dispatch($contract);

            return $contract;
        });
    }
}
