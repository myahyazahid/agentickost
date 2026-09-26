<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\States\Contract\Draft;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * One of several residents leaves while the contract continues. The last
 * resident cannot leave this way; end the contract instead. If the primary
 * resident leaves, the longest-staying remaining resident becomes primary.
 */
final class RemoveResidentFromContract extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, Resident $resident, array $input = []): void
    {
        $this->authorize('update', $contract);

        $occupant = $contract->occupants()->where('resident_id', $resident->id)->whereNull('left_on')->first();

        if ($occupant === null) {
            throw ValidationException::withMessages(['resident_id' => "{$resident->full_name} tidak tinggal di kontrak ini."]);
        }

        if ($contract->occupants()->whereNull('left_on')->count() === 1) {
            throw ValidationException::withMessages(['resident_id' => 'Penghuni terakhir tidak bisa dikeluarkan. Akhiri kontraknya.']);
        }

        $isDraft = $contract->status->equals(Draft::class);

        $data = $isDraft ? [] : $this->validate($input, [
            'left_on' => ['required', 'date', 'after_or_equal:'.$occupant->joined_on->toDateString()],
        ]);

        $this->transaction(function () use ($contract, $occupant, $isDraft, $data): void {
            $wasPrimary = $occupant->is_primary;

            if ($isDraft) {
                $occupant->delete();
            } else {
                $occupant->update(['left_on' => $data['left_on'], 'is_primary' => false]);
            }

            if ($wasPrimary) {
                $contract->occupants()
                    ->whereNull('left_on')
                    ->oldest('joined_on')
                    ->first()
                    ?->update(['is_primary' => true]);
            }
        });
    }
}
