<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Finance\Events\DepositRefunded;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Models\Contract;
use App\Support\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Pays deposit back to the resident from a cash or bank account. Never more
 * than the deposit held (FR-DEP-03).
 */
final class RefundDeposit extends Action
{
    public function __construct(private readonly DepositLedger $deposits) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, array $input): DepositTransaction
    {
        $this->authorize('manageFor', [DepositTransaction::class, $contract]);

        $data = $this->validate($input, [
            'amount' => ['required', 'integer', 'min:1'],
            'account_id' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $account = self::payoutAccounts()->whereKey($data['account_id'])->first()
            ?? throw ValidationException::withMessages(['account_id' => 'Pilih rekening atau kas asal pengembalian.']);

        return $this->transaction(function () use ($contract, $data, $account): DepositTransaction {
            $contract = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            $entry = $this->deposits->record($contract, DepositTransactionType::Refunded, -(int) $data['amount'], [
                'account_id' => $account->id,
                'reason' => $data['reason'] ?? null,
            ]);

            DepositRefunded::dispatch($entry);

            return $entry;
        });
    }

    /**
     * Cash and bank accounts money can be paid out of: the cash account and
     * each bank account, not the grouping account above them.
     *
     * @return Builder<Account>
     */
    public static function payoutAccounts(): Builder
    {
        return Account::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->where('subtype', AccountSubtype::Cash->value)
                ->orWhere(fn ($query) => $query->where('subtype', AccountSubtype::Bank->value)->whereNotNull('parent_id')))
            ->orderBy('code');
    }
}
