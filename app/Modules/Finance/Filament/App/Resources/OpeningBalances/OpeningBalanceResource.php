<?php

namespace App\Modules\Finance\Filament\App\Resources\OpeningBalances;

use App\Modules\Finance\Filament\App\Resources\OpeningBalances\Pages\CreateOpeningBalance;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\Pages\EditOpeningBalance;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\Pages\ListOpeningBalances;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\Pages\ViewOpeningBalance;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\Schemas\OpeningBalanceForm;
use App\Modules\Finance\Models\OpeningBalance;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Balances carried in from the owner's books at onboarding (FR-ONB-04).
 */
class OpeningBalanceResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = OpeningBalance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'saldo awal';

    protected static ?string $pluralModelLabel = 'saldo awal';

    protected static ?string $slug = 'saldo-awal';

    public static function form(Schema $schema): Schema
    {
        return OpeningBalanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cutoff_date')->label('Tanggal cut-off')->date('j M Y')->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('lines_count')->label('Rincian')->counts('lines')->suffix(' baris'),
                TextColumn::make('posted_at')->label('Diposting')->since()->placeholder('Belum'),
            ])
            ->defaultSort('cutoff_date', 'desc')
            ->recordUrl(fn (OpeningBalance $record): string => static::getUrl($record->isDraft() ? 'edit' : 'view', ['record' => $record]))
            ->emptyStateIcon(Heroicon::OutlinedScale)
            ->emptyStateHeading('Belum ada saldo awal')
            ->emptyStateDescription('Catat tunggakan, deposit yang dipegang, dan saldo kas per tanggal mulai memakai Agentic Kost, supaya laporan cocok dengan catatan lama.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpeningBalances::route('/'),
            'create' => CreateOpeningBalance::route('/catat'),
            'edit' => EditOpeningBalance::route('/{record}/ubah'),
            'view' => ViewOpeningBalance::route('/{record}'),
        ];
    }
}
