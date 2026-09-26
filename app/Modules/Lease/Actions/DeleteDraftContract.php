<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractHold;
use App\Modules\Lease\Models\ContractResident;
use App\Modules\Lease\States\Contract\Draft;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Discards a contract that never started. Started contracts are ended, never
 * deleted.
 */
final class DeleteDraftContract extends Action
{
    public function handle(Contract $contract): void
    {
        $this->authorize('delete', $contract);

        if (! $contract->status->equals(Draft::class)) {
            throw ValidationException::withMessages(['status' => 'Hanya kontrak draf yang bisa dihapus.']);
        }

        $this->transaction(function () use ($contract): void {
            $contract->occupants()->get()->each(fn (ContractResident $occupant) => $occupant->delete());
            $contract->holds()->get()->each(fn (ContractHold $hold) => $hold->delete());
            $contract->delete();
        });
    }
}
