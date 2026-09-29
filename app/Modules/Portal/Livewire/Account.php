<?php

namespace App\Modules\Portal\Livewire;

use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\States\Contract\ContractState;
use Illuminate\Contracts\View\View;

/**
 * The login's contracts with their deposit balance and PDF (FR-PRT-02),
 * and logging out.
 */
class Account extends PortalPage
{
    public function render(): View
    {
        $access = $this->access();
        $contracts = $access->contracts()
            ->with(['room', 'property'])
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', ContractState::runningValues())
            ->orderByDesc('start_date')
            ->get();

        return $this->page('portal::livewire.account', 'Akun', [
            'account' => $this->account(),
            'contracts' => $contracts,
            'deposits' => $contracts->mapWithKeys(fn ($contract): array => [$contract->id => DepositLedger::balance($contract->id)]),
            'livesIn' => fn ($contract): bool => $access->livesIn($contract),
        ]);
    }
}
