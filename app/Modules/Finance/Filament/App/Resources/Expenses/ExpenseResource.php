<?php

namespace App\Modules\Finance\Filament\App\Resources\Expenses;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Actions\VoidExpense;
use App\Modules\Finance\Filament\App\Resources\Expenses\Pages\ListExpenses;
use App\Modules\Finance\Filament\App\Resources\Expenses\Pages\RecordExpense;
use App\Modules\Finance\Models\Expense;
use App\Modules\Property\Filament\PropertyOptions;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyColumn;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Money spent per property (FR-ACC-03).
 */
class ExpenseResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'pengeluaran';

    protected static ?string $pluralModelLabel = 'pengeluaran';

    protected static ?string $slug = 'pengeluaran';

    /**
     * @return Builder<Expense>
     */
    public static function getEloquentQuery(): Builder
    {
        return Expense::query()->accessibleBy(User::current());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('spent_on')->label('Tanggal')->date('j M Y')->sortable(),
                TextColumn::make('description')
                    ->label('Keterangan')
                    ->description(fn (Expense $record): string => ($record->expenseAccount->name ?? '-').', '.($record->property->name ?? '-'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('paidFromAccount.name')->label('Dibayar dari'),
                MoneyColumn::make('amount')
                    ->label('Jumlah')
                    ->description(fn (Expense $record): ?string => $record->isVoided() ? "Dibatalkan: {$record->void_reason}" : null)
                    ->color(fn (Expense $record): ?string => $record->isVoided() ? 'gray' : null),
            ])
            ->defaultSort('spent_on', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['expenseAccount', 'paidFromAccount', 'property']))
            ->filters([
                SelectFilter::make('property_id')->label('Properti')->options(fn (): array => PropertyOptions::properties()),
                SelectFilter::make('expense_account_id')->label('Kategori')->relationship('expenseAccount', 'name'),
                TernaryFilter::make('voided_at')
                    ->label('Dibatalkan')
                    ->nullable()
                    ->placeholder('Semua')
                    ->trueLabel('Hanya yang dibatalkan')
                    ->falseLabel('Hanya yang berlaku')
                    ->default(false),
            ])
            ->recordActions([
                Action::make('void')
                    ->label('Batalkan')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Expense $record): bool => ! $record->isVoided() && User::current()->can('void', $record))
                    ->modalHeading('Batalkan pengeluaran ini?')
                    ->modalDescription('Pengeluaran tetap tersimpan dengan status dibatalkan dan jurnalnya dibalik. Bila jumlahnya salah, catat ulang dengan jumlah yang benar.')
                    ->schema([
                        Textarea::make('reason')->label('Alasan')->required()->minLength(5),
                    ])
                    ->modalSubmitActionLabel('Batalkan pengeluaran')
                    ->action(function (Action $action, Expense $record, array $data): void {
                        DomainActions::forAction($action, fn () => app(VoidExpense::class)->handle($record, $data));
                    }),
            ])
            ->emptyStateIcon(Heroicon::OutlinedReceiptPercent)
            ->emptyStateHeading('Belum ada pengeluaran')
            ->emptyStateDescription('Catat biaya seperti token listrik, air, perbaikan, atau kebersihan per properti.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenses::route('/'),
            'create' => RecordExpense::route('/catat'),
        ];
    }
}
