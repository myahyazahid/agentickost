<?php

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\JournalLine;
use Illuminate\Database\Eloquent\Builder;

/**
 * Balances read from the journal lines (the general ledger), for checking
 * them against payments, deposits, and credit.
 */
final class LedgerBalances
{
    /**
     * Balance on the account's normal side: debit minus credit for assets
     * and expenses, credit minus debit for the rest.
     */
    public static function of(Account $account, ?string $contractId = null): int
    {
        $net = self::net(JournalLine::query()->where('account_id', $account->id), $contractId);

        return $account->type->isDebitNormal() ? $net : -$net;
    }

    /**
     * Balance of the tenant's system account for a subtype, optionally for
     * one contract's sub-ledger.
     */
    public static function ofSubtype(AccountSubtype $subtype, ?string $contractId = null): int
    {
        return self::of(Account::system($subtype), $contractId);
    }

    /**
     * Accounts with `debit_total` and `credit_total` selected, for the trial
     * balance.
     *
     * @return Builder<Account>
     */
    public static function withTotals(): Builder
    {
        return Account::query()
            ->select('accounts.*')
            ->selectSub(JournalLine::query()->selectRaw('COALESCE(SUM(debit_amount), 0)')->whereColumn('journal_lines.account_id', 'accounts.id'), 'debit_total')
            ->selectSub(JournalLine::query()->selectRaw('COALESCE(SUM(credit_amount), 0)')->whereColumn('journal_lines.account_id', 'accounts.id'), 'credit_total');
    }

    /**
     * @return array{debit: int, credit: int}
     */
    public static function trialTotals(): array
    {
        $row = JournalLine::query()->selectRaw('COALESCE(SUM(debit_amount), 0) AS debit, COALESCE(SUM(credit_amount), 0) AS credit')->toBase()->first();

        return ['debit' => (int) ($row->debit ?? 0), 'credit' => (int) ($row->credit ?? 0)];
    }

    /**
     * @param  Builder<JournalLine>  $query
     */
    private static function net(Builder $query, ?string $contractId): int
    {
        if ($contractId !== null) {
            $query->where('contract_id', $contractId);
        }

        return (int) (clone $query)->sum('debit_amount') - (int) (clone $query)->sum('credit_amount');
    }
}
