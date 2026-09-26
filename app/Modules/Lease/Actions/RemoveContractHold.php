<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Lease\Models\ContractHold;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Removes a hold that has not started yet. A started hold may already be on
 * an invoice, so it stays.
 */
final class RemoveContractHold extends Action
{
    public function handle(ContractHold $hold): void
    {
        $contract = $hold->contract()->firstOrFail();

        $this->authorize('update', $contract);

        if ($hold->start_date->lessThanOrEqualTo($contract->property()->firstOrFail()->today())) {
            throw ValidationException::withMessages(['hold' => 'Masa hold yang sudah dimulai tidak bisa dihapus.']);
        }

        $this->transaction(fn () => $hold->delete());
    }
}
