<?php

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\JournalLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Profit and loss, balance sheet, and cash movements read from the journal
 * (FR-ACC-06, FR-ACC-07), for one property or the whole business. A line
 * belongs to a property through its own property_id; lines without one
 * (tenant-wide entries) only appear in the consolidated view.
 */
final class FinancialStatements
{
    /**
     * Subtypes whose accounts hold money: cash, staff cash, and bank.
     *
     * @var list<AccountSubtype>
     */
    public const CASH_SUBTYPES = [AccountSubtype::Cash, AccountSubtype::StaffCash, AccountSubtype::Bank];

    /**
     * Revenue and expense accounts over the period, each on its normal
     * side, so revenue minus expenses is the profit.
     *
     * @return array{revenue: list<StatementLine>, expense: list<StatementLine>}
     */
    public static function profitAndLoss(CarbonImmutable $from, CarbonImmutable $to, ?string $propertyId): array
    {
        $totals = self::accountTotals($from, $to, $propertyId, [AccountType::Revenue, AccountType::Expense]);

        return [
            'revenue' => self::linesOf($totals, AccountType::Revenue),
            'expense' => self::linesOf($totals, AccountType::Expense),
        ];
    }

    /**
     * Balances at the end of the given day. Profit is never closed into
     * equity by an entry, so revenue minus expenses to date is shown as
     * the equity line "Laba ditahan dan laba berjalan".
     *
     * @return array{asset: list<StatementLine>, liability: list<StatementLine>, equity: list<StatementLine>, earnings: int}
     */
    public static function balanceSheet(CarbonImmutable $asOf, ?string $propertyId): array
    {
        $totals = self::accountTotals(null, $asOf, $propertyId, AccountType::cases());

        $earnings = array_sum(array_map(fn (StatementLine $line): int => $line->amount, self::linesOf($totals, AccountType::Revenue)))
            - array_sum(array_map(fn (StatementLine $line): int => $line->amount, self::linesOf($totals, AccountType::Expense)));

        return [
            'asset' => self::linesOf($totals, AccountType::Asset),
            'liability' => self::linesOf($totals, AccountType::Liability),
            'equity' => self::linesOf($totals, AccountType::Equity),
            'earnings' => $earnings,
        ];
    }

    /**
     * Money in and out of the cash, staff cash, and bank accounts over the
     * period, grouped by what it was for. Transfers between those accounts
     * (such as a staff handover) cancel out. Opening balances entered at
     * go-live count toward the opening cash, not as movements.
     *
     * @return array{opening: int, closing: int, movements: list<CashMovement>}
     */
    public static function cashMovements(CarbonImmutable $from, CarbonImmutable $to, ?string $propertyId): array
    {
        $cashAccounts = Account::query()->whereIn('subtype', array_map(fn (AccountSubtype $subtype): string => $subtype->value, self::CASH_SUBTYPES))->pluck('id')->all();

        $cashLines = fn (): Builder => self::lines($propertyId)->whereIn('journal_lines.account_id', $cashAccounts);
        $net = fn (Builder $query): int => (int) (clone $query)->sum('journal_lines.debit_amount') - (int) (clone $query)->sum('journal_lines.credit_amount');

        $opening = $net($cashLines()->where('journal_entries.entry_date', '<', $from->toDateString()))
            + $net($cashLines()->whereBetween('journal_entries.entry_date', [$from->toDateString(), $to->toDateString()])->where('journal_entries.event', JournalEvent::OpeningBalance->value));
        $closing = $net($cashLines()->where('journal_entries.entry_date', '<=', $to->toDateString()));

        $rows = self::lines($propertyId)
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->whereBetween('journal_entries.entry_date', [$from->toDateString(), $to->toDateString()])
            ->where('journal_entries.event', '!=', JournalEvent::OpeningBalance->value)
            ->whereNotIn('journal_lines.account_id', $cashAccounts)
            ->whereIn('journal_lines.journal_entry_id', JournalLine::query()->select('journal_entry_id')->whereIn('account_id', $cashAccounts))
            ->groupBy('journal_entries.event', 'accounts.id', 'accounts.name', 'accounts.type', 'accounts.subtype')
            ->selectRaw('journal_entries.event, accounts.name, accounts.type, accounts.subtype, SUM(journal_lines.credit_amount) - SUM(journal_lines.debit_amount) AS amount')
            ->toBase()
            ->get();

        $movements = [];

        foreach ($rows as $row) {
            $movement = CashMovement::classify(
                JournalEvent::from((string) $row->event),
                AccountType::from((string) $row->type),
                AccountSubtype::tryFrom((string) $row->subtype),
                (string) $row->name,
                (int) $row->amount,
            );

            if ($movement->amount === 0) {
                continue;
            }

            $key = $movement->activity->value.'|'.$movement->label;
            $movements[$key] = isset($movements[$key]) ? $movements[$key]->plus($movement->amount) : $movement;
        }

        return ['opening' => $opening, 'closing' => $closing, 'movements' => array_values($movements)];
    }

    /**
     * @param  list<AccountType>  $types
     * @return Collection<int, object{code: string, name: string, type: string, debit: string|int, credit: string|int}>
     */
    private static function accountTotals(?CarbonImmutable $from, CarbonImmutable $to, ?string $propertyId, array $types): Collection
    {
        /** @var Collection<int, object{code: string, name: string, type: string, debit: string|int, credit: string|int}> */
        return self::lines($propertyId)
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->whereIn('accounts.type', array_map(fn (AccountType $type): string => $type->value, $types))
            ->when($from !== null, fn (Builder $query) => $query->where('journal_entries.entry_date', '>=', $from?->toDateString()))
            ->where('journal_entries.entry_date', '<=', $to->toDateString())
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->selectRaw('accounts.code, accounts.name, accounts.type, SUM(journal_lines.debit_amount) AS debit, SUM(journal_lines.credit_amount) AS credit')
            ->toBase()
            ->get();
    }

    /**
     * @param  Collection<int, object{code: string, name: string, type: string, debit: string|int, credit: string|int}>  $totals
     * @return list<StatementLine>
     */
    private static function linesOf(Collection $totals, AccountType $type): array
    {
        $lines = [];

        foreach ($totals->where('type', $type->value) as $row) {
            $net = (int) $row->debit - (int) $row->credit;
            $amount = $type->isDebitNormal() ? $net : -$net;

            if ($amount !== 0) {
                $lines[] = new StatementLine($row->name, $amount, $row->code);
            }
        }

        return $lines;
    }

    /**
     * @return Builder<JournalLine>
     */
    private static function lines(?string $propertyId): Builder
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when($propertyId !== null, fn (Builder $query) => $query->where('journal_lines.property_id', $propertyId));
    }
}
