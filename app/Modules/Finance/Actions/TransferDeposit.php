<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Finance\Events\DepositTransferred;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Lease\States\Contract\Draft;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Moves deposit from one contract to another of the same tenant, such as to
 * a renewal or a new room (FR-DEP-01). Both ledgers get an entry pointing at
 * the other contract.
 */
final class TransferDeposit extends Action
{
    public function __construct(private readonly DepositLedger $deposits) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $from, array $input): DepositTransaction
    {
        $this->authorize('manageFor', [DepositTransaction::class, $from]);

        $data = $this->validate($input, [
            'to_contract_id' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $to = Contract::query()
            ->whereKey($data['to_contract_id'])
            ->whereKeyNot($from->id)
            ->whereIn('status', [Draft::$name, ...ContractState::runningValues()])
            ->first() ?? throw ValidationException::withMessages(['to_contract_id' => 'Kontrak tujuan harus draf atau masih berjalan.']);

        $this->authorize('manageFor', [DepositTransaction::class, $to]);

        return $this->transaction(function () use ($from, $to, $data): DepositTransaction {
            [$first, $second] = strcmp($from->id, $to->id) < 0 ? [$from, $to] : [$to, $from];
            Contract::query()->whereKey($first->id)->lockForUpdate()->firstOrFail();
            Contract::query()->whereKey($second->id)->lockForUpdate()->firstOrFail();

            $out = $this->deposits->record($from, DepositTransactionType::Transferred, -(int) $data['amount'], [
                'related_contract_id' => $to->id,
                'reason' => $data['reason'] ?? null,
            ]);

            $this->deposits->record($to, DepositTransactionType::Transferred, (int) $data['amount'], [
                'related_contract_id' => $from->id,
                'reason' => $data['reason'] ?? null,
            ]);

            DepositTransferred::dispatch($out);

            return $out;
        });
    }
}
