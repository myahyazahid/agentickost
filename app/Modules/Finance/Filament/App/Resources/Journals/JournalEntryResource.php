<?php

namespace App\Modules\Finance\Filament\App\Resources\Journals;

use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Filament\App\Resources\Journals\Pages\ListJournalEntries;
use App\Modules\Finance\Filament\App\Resources\Journals\Pages\ViewJournalEntry;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Property\Filament\PropertyOptions;
use App\Support\Filament\MoneyColumn;
use App\Support\Filament\SentenceCaseLabels;
use App\Support\Money\Rupiah;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Automatic journals, read-only (FR-ACC-02).
 */
class JournalEntryResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = JournalEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Jurnal';

    protected static ?int $navigationSort = 21;

    protected static ?string $modelLabel = 'jurnal';

    protected static ?string $pluralModelLabel = 'jurnal';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $slug = 'jurnal';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_date')->label('Tanggal')->date('j M Y')->sortable(),
                TextColumn::make('number')
                    ->label('Nomor')
                    ->description(fn (JournalEntry $record): string => $record->description)
                    ->searchable(['number', 'description'])
                    ->wrap(),
                TextColumn::make('event')->label('Peristiwa')->badge()->color('gray'),
                MoneyColumn::make('total')
                    ->label('Jumlah')
                    ->state(fn (JournalEntry $record): int => (int) $record->getAttribute('total')),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withSum('lines as total', 'debit_amount'))
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('entry_date')->orderByDesc('id'))
            ->filters([
                SelectFilter::make('event')->label('Peristiwa')->options(JournalEvent::class),
                SelectFilter::make('property_id')->label('Properti')->options(fn (): array => PropertyOptions::properties()),
            ])
            ->recordActions([ViewAction::make()->label('Buka')])
            ->emptyStateIcon(Heroicon::OutlinedQueueList)
            ->emptyStateHeading('Belum ada jurnal')
            ->emptyStateDescription('Jurnal dibuat otomatis saat tagihan terbit, pembayaran diverifikasi, deposit berubah, atau pengeluaran dicatat.');
    }

    public static function infolist(Schema $schema): Schema
    {
        $money = fn (mixed $state): ?string => is_numeric($state) && (int) $state !== 0 ? Rupiah::format((int) $state) : null;

        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('entry_date')->label('Tanggal')->date('j F Y'),
                    TextEntry::make('event')->label('Peristiwa'),
                    TextEntry::make('property.name')->label('Properti')->placeholder('-'),
                    TextEntry::make('description')->label('Keterangan')->columnSpanFull(),
                    TextEntry::make('reversalOf.number')->label('Membalik jurnal')->placeholder('-'),
                ]),
            Section::make('Baris jurnal')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('lines')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Akun'),
                            TableColumn::make('Kontrak'),
                            TableColumn::make('Debit')->alignment(Alignment::End),
                            TableColumn::make('Kredit')->alignment(Alignment::End),
                        ])
                        ->schema([
                            TextEntry::make('account.name')
                                ->label('Akun')
                                ->state(fn (JournalLine $record): string => "{$record->account?->code} {$record->account?->name}"),
                            TextEntry::make('contract.number')->label('Kontrak')->placeholder('-'),
                            TextEntry::make('debit_amount')->label('Debit')->formatStateUsing($money)->placeholder('-')->alignEnd(),
                            TextEntry::make('credit_amount')->label('Kredit')->formatStateUsing($money)->placeholder('-')->alignEnd(),
                        ]),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJournalEntries::route('/'),
            'view' => ViewJournalEntry::route('/{record}'),
        ];
    }
}
