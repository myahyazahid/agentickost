<?php

namespace App\Modules\Finance\Filament\App\Resources\BankAccounts;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\BankAccountKind;
use App\Modules\Finance\Filament\App\Resources\BankAccounts\Pages\CreateBankAccount;
use App\Modules\Finance\Filament\App\Resources\BankAccounts\Pages\EditBankAccount;
use App\Modules\Finance\Filament\App\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Property;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Transfer destinations shown to residents (FR-PRP-03).
 */
class BankAccountResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = BankAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 90;

    protected static ?string $modelLabel = 'rekening tujuan';

    protected static ?string $pluralModelLabel = 'rekening tujuan';

    protected static ?string $slug = 'rekening-tujuan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('kind')
                        ->label('Jenis')
                        ->options(BankAccountKind::class)
                        ->default(BankAccountKind::Bank->value)
                        ->required(),
                    TextInput::make('provider_name')
                        ->label('Bank atau e-wallet')
                        ->placeholder('Misal: BCA')
                        ->required()
                        ->maxLength(80),
                    TextInput::make('account_number')
                        ->label('Nomor rekening')
                        ->required()
                        ->maxLength(40),
                    TextInput::make('account_holder')
                        ->label('Atas nama')
                        ->required()
                        ->maxLength(100),
                    Select::make('property_id')
                        ->label('Dipakai untuk properti')
                        ->placeholder('Semua properti')
                        ->options(fn (): array => PropertyOptions::properties()),
                    Toggle::make('is_default')
                        ->label('Rekening utama')
                        ->helperText('Ditampilkan paling atas di tagihan.'),
                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->helperText('Rekening nonaktif tidak ditampilkan di tagihan baru.')
                        ->default(true)
                        ->visibleOn('edit'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider_name')
                    ->label('Rekening')
                    ->description(fn (BankAccount $record): string => "{$record->account_number} a.n. {$record->account_holder}")
                    ->searchable(['provider_name', 'account_number', 'account_holder']),
                TextColumn::make('property.name')
                    ->label('Properti')
                    ->placeholder('Semua properti'),
                TextColumn::make('kind')->label('Jenis')->badge(),
                IconColumn::make('is_default')->label('Utama')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedBanknotes)
            ->emptyStateHeading('Belum ada rekening tujuan')
            ->emptyStateDescription('Tambahkan rekening tempat penghuni mentransfer pembayaran.');
    }

    /**
     * @return Builder<BankAccount>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = User::current();

        return BankAccount::query()->with('property')->where(fn (Builder $query) => $query
            ->whereNull('property_id')
            ->orWhereIn('property_id', Property::query()->accessibleBy($user)->select('id')));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankAccounts::route('/'),
            'create' => CreateBankAccount::route('/tambah'),
            'edit' => EditBankAccount::route('/{record}/ubah'),
        ];
    }
}
