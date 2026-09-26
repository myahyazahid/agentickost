<?php

namespace App\Modules\Billing\Filament\App\Resources\UtilityRates;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Actions\SetUtilityRate;
use App\Modules\Billing\Enums\MeterUnit;
use App\Modules\Billing\Enums\UtilityKind;
use App\Modules\Billing\Enums\UtilityMode;
use App\Modules\Billing\Filament\App\Resources\UtilityRates\Pages\ManageUtilityRates;
use App\Modules\Billing\Models\UtilityRate;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Property;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyInput;
use App\Support\Filament\SentenceCaseLabels;
use App\Support\Money\Rupiah;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * How each property charges electricity, water, and internet (FR-UTL-01).
 * Rates are never edited: a new rate closes the previous one.
 */
class UtilityRateResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = UtilityRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Properti';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'tarif utilitas';

    protected static ?string $pluralModelLabel = 'tarif utilitas';

    protected static ?string $slug = 'tarif-utilitas';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('property.name')->label('Properti'),
                TextColumn::make('utility')->label('Utilitas'),
                TextColumn::make('mode')->label('Cara tagih'),
                TextColumn::make('rate_amount')
                    ->label('Tarif')
                    ->state(fn (UtilityRate $record): string => match ($record->mode) {
                        UtilityMode::Metered => Rupiah::format($record->rate_amount).' per '.$record->unit?->getLabel(),
                        UtilityMode::Flat => Rupiah::format($record->rate_amount).' per bulan',
                        UtilityMode::Token => 'Dibeli sendiri oleh penghuni',
                    }),
                TextColumn::make('effective_from')
                    ->label('Berlaku')
                    ->date('j M Y')
                    ->description(fn (UtilityRate $record): string => $record->effective_until === null
                        ? 'sampai sekarang'
                        : 'sampai '.$record->effective_until->translatedFormat('j M Y'))
                    ->icon(fn (UtilityRate $record): Heroicon => $record->effective_until === null ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedArchiveBox)
                    ->iconColor(fn (UtilityRate $record): string => $record->effective_until === null ? 'success' : 'gray'),
            ])
            ->defaultSort('effective_from', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('property'))
            ->filters([
                SelectFilter::make('property_id')
                    ->label('Properti')
                    ->options(fn (): array => PropertyOptions::properties()),
                SelectFilter::make('utility')
                    ->label('Utilitas')
                    ->options(UtilityKind::class),
            ])
            ->emptyStateIcon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->emptyStateHeading('Belum ada tarif utilitas')
            ->emptyStateDescription('Atur cara menagih listrik, air, dan internet. Tanpa tarif, utilitas tidak ikut ditagihkan.');
    }

    public static function setRateAction(): Action
    {
        return Action::make('setRate')
            ->label('Atur tarif')
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => Property::query()->accessibleBy(User::current())->get()
                ->contains(fn (Property $property): bool => User::current()->can('createIn', [UtilityRate::class, $property])))
            ->modalDescription('Tarif baru berlaku mulai tanggal yang dipilih. Tarif sebelumnya otomatis berakhir sehari sebelumnya. Pencatatan meteran yang sudah ada tetap memakai tarif lamanya.')
            ->schema([
                Select::make('property_id')
                    ->label('Properti')
                    ->options(fn (): array => PropertyOptions::properties())
                    ->default(fn (): ?string => count($options = PropertyOptions::properties()) === 1 ? array_key_first($options) : null)
                    ->required(),
                Select::make('utility')->label('Utilitas')->options(UtilityKind::class)->required(),
                Select::make('mode')->label('Cara tagih')->options(UtilityMode::class)->required()->live(),
                Select::make('unit')
                    ->label('Satuan meteran')
                    ->options(MeterUnit::class)
                    ->visible(fn (Get $get): bool => self::modeIs($get('mode'), UtilityMode::Metered))
                    ->required(fn (Get $get): bool => self::modeIs($get('mode'), UtilityMode::Metered)),
                MoneyInput::make('rate_amount')
                    ->label(fn (Get $get): string => self::modeIs($get('mode'), UtilityMode::Flat) ? 'Tarif per bulan' : 'Tarif per satuan')
                    ->visible(fn (Get $get): bool => $get('mode') !== null && ! self::modeIs($get('mode'), UtilityMode::Token))
                    ->required(fn (Get $get): bool => $get('mode') !== null && ! self::modeIs($get('mode'), UtilityMode::Token)),
                DatePicker::make('effective_from')->label('Berlaku mulai')->default(now())->required(),
            ])
            ->action(function (Action $action, array $data): void {
                $property = Property::query()->accessibleBy(User::current())->whereKey($data['property_id'] ?? null)->firstOrFail();

                DomainActions::forAction($action, fn () => app(SetUtilityRate::class)->handle($property, $data));

                Notification::make()->success()->title('Tarif disimpan')->send();
            });
    }

    /**
     * @return Builder<UtilityRate>
     */
    public static function getEloquentQuery(): Builder
    {
        return UtilityRate::query()->accessibleBy(User::current());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUtilityRates::route('/'),
        ];
    }

    private static function modeIs(mixed $state, UtilityMode $mode): bool
    {
        return $state === $mode || $state === $mode->value;
    }
}
