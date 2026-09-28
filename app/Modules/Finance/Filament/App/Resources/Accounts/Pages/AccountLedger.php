<?php

namespace App\Modules\Finance\Filament\App\Resources\Accounts\Pages;

use App\Modules\Finance\Filament\App\Resources\Accounts\AccountResource;
use App\Modules\Finance\Filament\App\Resources\Journals\JournalEntryResource;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Support\LedgerBalances;
use App\Support\Filament\MoneyColumn;
use App\Support\Money\Rupiah;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * One account's ledger: every journal line on it, oldest first, with the
 * running balance, to check the automatic journals.
 */
class AccountLedger extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = AccountResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(AccountResource::canViewAny(), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return "Buku besar {$this->account()->code} {$this->account()->name}";
    }

    public function getSubheading(): string
    {
        return 'Saldo '.Rupiah::format(LedgerBalances::of($this->account())).'.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $sign = $this->account()->type->isDebitNormal() ? '' : '-';

        return $table
            ->query(fn (): Builder => JournalLine::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_lines.account_id', $this->account()->id)
                ->select('journal_lines.*', 'journal_entries.entry_date', 'journal_entries.number', 'journal_entries.description')
                ->selectRaw("{$sign}SUM(journal_lines.debit_amount - journal_lines.credit_amount) OVER (ORDER BY journal_entries.entry_date, journal_lines.id) AS running_balance")
                ->with('contract'))
            ->defaultSort(fn (Builder $query) => $query->orderBy('journal_entries.entry_date')->orderBy('journal_lines.id'))
            ->columns([
                TextColumn::make('entry_date')->label('Tanggal')->date('j M Y'),
                TextColumn::make('number')
                    ->label('Jurnal')
                    ->description(fn (JournalLine $record): string => (string) $record->getAttribute('description'))
                    ->url(fn (JournalLine $record): string => JournalEntryResource::getUrl('view', ['record' => $record->journal_entry_id]))
                    ->wrap(),
                MoneyColumn::make('debit_amount')->label('Debit')->placeholder('-'),
                MoneyColumn::make('credit_amount')->label('Kredit')->placeholder('-'),
                MoneyColumn::make('running_balance')->label('Saldo'),
            ])
            ->emptyStateHeading('Belum ada transaksi di akun ini');
    }

    private function account(): Account
    {
        $record = $this->getRecord();

        return $record instanceof Account ? $record : throw new LogicException('Buku besar hanya untuk akun.');
    }
}
