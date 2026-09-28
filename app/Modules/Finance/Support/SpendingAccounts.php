<?php

namespace App\Modules\Finance\Support;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Actions\RefundDeposit;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Finance\Models\Account;
use Illuminate\Database\Eloquent\Builder;

/**
 * Accounts an expense can be booked to and paid from (FR-ACC-03, FR-ACC-04).
 * Owners and managers pay from the tenant's cash and bank accounts; anyone
 * holding staff cash can pay from their own.
 */
final class SpendingAccounts
{
    /**
     * @return Builder<Account>
     */
    public static function paidFrom(User $user): Builder
    {
        $ownCash = Account::query()->where('user_id', $user->id)->value('id');
        $companyMoney = $user->can(FinancePermission::SpendFromCashAndBank->value);

        return Account::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->when($companyMoney, fn (Builder $query) => $query->orWhereIn('id', RefundDeposit::payoutAccounts()->select('id')))
                ->when($ownCash !== null, fn (Builder $query) => $query->orWhere('id', $ownCash))
                ->when(! $companyMoney && $ownCash === null, fn (Builder $query) => $query->whereRaw('1 = 0')))
            ->orderBy('code');
    }

    /**
     * @return Builder<Account>
     */
    public static function expenseAccounts(): Builder
    {
        return Account::query()
            ->where('type', AccountType::Expense->value)
            ->where('is_active', true)
            ->orderBy('code');
    }
}
