<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Support\ChartOfAccounts;
use App\Support\Actions\Action;

/**
 * Creates the built-in accounts of the current tenant. Safe to run again.
 *
 * Internal provisioning, called when a tenant is created, so it does not
 * authorize.
 */
final class ProvisionChartOfAccounts extends Action
{
    public function handle(): void
    {
        $this->transaction(function (): void {
            foreach (ChartOfAccounts::defaults() as $account) {
                Account::query()->firstOrCreate(
                    ['code' => $account['code']],
                    [...$account, 'is_system' => true],
                );
            }
        });
    }
}
