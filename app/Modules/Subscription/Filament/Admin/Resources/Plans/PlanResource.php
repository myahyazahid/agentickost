<?php

namespace App\Modules\Subscription\Filament\Admin\Resources\Plans;

use App\Modules\Subscription\Enums\PlanFeature;
use App\Modules\Subscription\Filament\Admin\Resources\Plans\Pages\CreatePlan;
use App\Modules\Subscription\Filament\Admin\Resources\Plans\Pages\EditPlan;
use App\Modules\Subscription\Filament\Admin\Resources\Plans\Pages\ListPlans;
use App\Modules\Subscription\Models\Plan;
use App\Support\Filament\MoneyColumn;
use App\Support\Filament\MoneyInput;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Subscription plans with their prices, limits, and features (FR-SUB-01,
 * FR-SUB-02). A plan in use is taken off sale rather than deleted.
 */
class PlanResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Langganan';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'paket';

    protected static ?string $pluralModelLabel = 'paket';

    protected static ?string $slug = 'paket';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Paket')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Nama')->required()->maxLength(80),
                    TextInput::make('code')
                        ->label('Kode')
                        ->helperText('Huruf, angka, dan tanda hubung. Misal: dasar.')
                        ->required()
                        ->maxLength(40),
                    Textarea::make('description')->label('Keterangan')->rows(2)->maxLength(1000)->columnSpanFull(),
                    MoneyInput::make('monthly_price_amount')->label('Harga per bulan')->required(),
                    MoneyInput::make('yearly_price_amount')
                        ->label('Harga per tahun')
                        ->helperText('Kosongkan bila paket ini tidak dijual tahunan.'),
                    TextInput::make('sort_order')->label('Urutan tampil')->numeric()->integer()->minValue(0)->default(0),
                    Toggle::make('is_active')
                        ->label('Dijual')
                        ->helperText('Paket yang tidak dijual tetap berlaku bagi tenant yang sudah memakainya.')
                        ->default(true),
                ]),
            Section::make('Batas')
                ->description('Kosongkan untuk tanpa batas.')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextInput::make('max_properties')->label('Properti')->numeric()->integer()->minValue(1),
                    TextInput::make('max_rooms')->label('Kamar')->numeric()->integer()->minValue(1),
                    TextInput::make('max_staff')->label('Pengguna')->numeric()->integer()->minValue(1),
                    TextInput::make('monthly_message_quota')
                        ->label('Pesan WhatsApp per bulan')
                        ->numeric()
                        ->integer()
                        ->minValue(0),
                    TextInput::make('monthly_ai_credit_quota')
                        ->label('Kredit AI per bulan')
                        ->numeric()
                        ->integer()
                        ->minValue(0),
                ]),
            Section::make('Fitur')
                ->columnSpanFull()
                ->schema([
                    CheckboxList::make('features')
                        ->hiddenLabel()
                        ->options(PlanFeature::class)
                        ->columns(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Paket')
                    ->description(fn (Plan $record): string => $record->code)
                    ->searchable(['name', 'code']),
                MoneyColumn::make('monthly_price_amount')->label('Per bulan'),
                MoneyColumn::make('yearly_price_amount')->label('Per tahun')->placeholder('-'),
                TextColumn::make('max_properties')->label('Properti')->placeholder('Tanpa batas'),
                TextColumn::make('max_rooms')->label('Kamar')->placeholder('Tanpa batas'),
                TextColumn::make('max_staff')->label('Pengguna')->placeholder('Tanpa batas'),
                IconColumn::make('is_active')->label('Dijual')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedRectangleStack)
            ->emptyStateHeading('Belum ada paket')
            ->emptyStateDescription('Buat paket supaya owner bisa memilih langganan setelah trial.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/tambah'),
            'edit' => EditPlan::route('/{record}/ubah'),
        ];
    }
}
