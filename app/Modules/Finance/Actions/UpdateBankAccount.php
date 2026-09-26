<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\BankAccount;
use App\Support\Actions\Action;

/**
 * Updates a transfer destination. Accounts are deactivated, never deleted,
 * because payments and journals point to them.
 */
final class UpdateBankAccount extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(BankAccount $bankAccount, array $input): BankAccount
    {
        $this->authorize('update', $bankAccount);

        $data = $this->validate($input, [
            ...CreateBankAccount::rules($bankAccount->tenant_id),
            'is_active' => ['boolean'],
        ]);

        return $this->transaction(function () use ($bankAccount, $data): BankAccount {
            $bankAccount->update($data);
            $bankAccount->ledgerAccount()->firstOrFail()->update([
                'name' => "{$bankAccount->provider_name} {$bankAccount->account_number}",
                'property_id' => $bankAccount->property_id,
                'is_active' => $bankAccount->is_active,
            ]);

            if ($bankAccount->is_default) {
                CreateBankAccount::clearOtherDefaults($bankAccount);
            }

            return $bankAccount;
        });
    }
}
