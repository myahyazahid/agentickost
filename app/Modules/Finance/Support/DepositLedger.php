<?php

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Lease\Models\Contract;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use App\Support\Money\Rupiah;
use Illuminate\Validation\ValidationException;

/**
 * Writes a contract's deposit ledger (FR-DEP-01). An entry that takes money
 * out may not leave the deposit below zero (FR-DEP-03). Call inside a
 * transaction that locked the contract row first (schema §14.1).
 */
final class DepositLedger
{
    public function __construct(private readonly ActorContext $actors) {}

    public static function balance(string $contractId): int
    {
        return (int) DepositTransaction::query()->where('contract_id', $contractId)->sum('amount');
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException when the deposit held is less than what is taken
     */
    public function record(Contract $contract, DepositTransactionType $type, int $amount, array $attributes = [], string $errorKey = 'amount'): DepositTransaction
    {
        if ($amount < 0) {
            $held = self::balance($contract->id);

            if ($held + $amount < 0) {
                throw ValidationException::withMessages([
                    $errorKey => 'Deposit yang dipegang untuk kontrak ini hanya '.Rupiah::format($held).'.',
                ]);
            }
        }

        $actor = $this->actors->current();

        return DepositTransaction::create([
            'contract_id' => $contract->id,
            'type' => $type,
            'amount' => $amount,
            'occurred_on' => $contract->property()->firstOrFail()->today(),
            'created_by' => $actor->type === ActorType::User ? $actor->id : null,
            ...$attributes,
        ]);
    }
}
