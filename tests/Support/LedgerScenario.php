<?php

namespace Tests\Support;

use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Support\LedgerBalances;

/**
 * Checks on the general ledger for journal tests.
 */
final class LedgerScenario
{
    /**
     * Entries whose debits and credits differ; must always be empty
     * (NFR-QA-02).
     *
     * @return list<string>
     */
    public static function unbalancedEntries(): array
    {
        return array_values(JournalEntry::query()
            ->whereIn('id', JournalLine::query()
                ->select('journal_entry_id')
                ->groupBy('journal_entry_id')
                ->havingRaw('SUM(debit_amount) <> SUM(credit_amount)'))
            ->pluck('number')
            ->all());
    }

    public static function balance(AccountSubtype $subtype, ?string $contractId = null): int
    {
        return LedgerBalances::ofSubtype($subtype, $contractId);
    }

    public static function account(Account|string $account): int
    {
        return LedgerBalances::of($account instanceof Account ? $account : Account::query()->findOrFail($account));
    }

    /**
     * Lines of the latest entry for an event as [account code, signed amount]
     * pairs, debits positive.
     *
     * @return list<array{0: string, 1: int}>
     */
    public static function lines(JournalEntry $entry): array
    {
        return array_values($entry->lines()
            ->with('account')
            ->orderBy('id')
            ->get()
            ->map(fn (JournalLine $line): array => [(string) $line->account?->code, $line->debit_amount - $line->credit_amount])
            ->all());
    }
}
