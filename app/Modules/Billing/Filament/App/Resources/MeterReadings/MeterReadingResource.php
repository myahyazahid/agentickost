<?php

namespace App\Modules\Billing\Filament\App\Resources\MeterReadings;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Actions\DeleteMeterReading;
use App\Modules\Billing\Enums\UtilityKind;
use App\Modules\Billing\Filament\App\Resources\MeterReadings\Pages\ListMeterReadings;
use App\Modules\Billing\Filament\App\Resources\MeterReadings\Pages\RecordMeterReading;
use App\Modules\Billing\Models\MeterReading;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Room;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyColumn;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Meter readings per room (FR-UTL-02). Usage is billed on the next rent
 * invoice of the room's contract (FR-UTL-04).
 */
class MeterReadingResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = MeterReading::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Tagihan';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'catatan meteran';

    protected static ?string $pluralModelLabel = 'catatan meteran';

    protected static ?string $navigationLabel = 'Meteran';

    protected static ?string $slug = 'meteran';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('room.number')
                    ->label('Kamar')
                    ->formatStateUsing(fn (string $state): string => "Kamar {$state}")
                    ->description(fn (MeterReading $record): ?string => $record->room?->property?->name)
                    ->searchable(),
                TextColumn::make('utility')->label('Utilitas'),
                TextColumn::make('reading_date')->label('Tanggal')->date('j M Y')->sortable(),
                TextColumn::make('current_value')
                    ->label('Angka meteran')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->description(fn (MeterReading $record): string => $record->is_meter_replaced
                        ? 'Meteran baru, mulai '.self::number($record->previous_value)
                        : 'Sebelumnya '.self::number($record->previous_value)),
                TextColumn::make('usage')
                    ->label('Pemakaian')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->placeholder('-'),
                MoneyColumn::make('amount')->label('Biaya'),
                TextColumn::make('billing_status')
                    ->label('Tagihan')
                    ->state(fn (MeterReading $record): string => match (true) {
                        $record->isBilled() => 'Sudah ditagih',
                        $record->amount === 0 => 'Angka awal',
                        default => 'Belum ditagih',
                    })
                    ->badge()
                    ->icon(fn (MeterReading $record): Heroicon => match (true) {
                        $record->isBilled() => Heroicon::OutlinedCheckCircle,
                        $record->amount === 0 => Heroicon::OutlinedFlag,
                        default => Heroicon::OutlinedClock,
                    })
                    ->color(fn (MeterReading $record): string => match (true) {
                        $record->isBilled() => 'success',
                        $record->amount === 0 => 'gray',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('reading_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['room.property', 'attachments']))
            ->filters([
                SelectFilter::make('property')
                    ->label('Properti')
                    ->options(fn (): array => PropertyOptions::properties())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $propertyId) => $query->whereHas('room', fn (Builder $room) => $room->where('property_id', $propertyId)),
                    )),
                SelectFilter::make('utility')->label('Utilitas')->options(UtilityKind::class),
                TernaryFilter::make('invoice_item_id')
                    ->label('Sudah ditagih')
                    ->nullable(),
            ])
            ->recordActions([
                Action::make('photo')
                    ->label('Foto')
                    ->icon(Heroicon::OutlinedCamera)
                    ->color('gray')
                    ->visible(fn (MeterReading $record): bool => $record->attachments->isNotEmpty())
                    ->url(fn (MeterReading $record): ?string => $record->attachments
                        ->first(fn (Attachment $attachment): bool => $attachment->collection === AttachmentCollection::Meter)
                        ?->temporaryUrl(30), shouldOpenInNewTab: true),
                Action::make('delete')
                    ->label('Hapus')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Catatan ini dihapus agar bisa dicatat ulang. Hanya catatan terakhir yang belum ditagih yang bisa dihapus.')
                    ->visible(fn (MeterReading $record): bool => User::current()->can('delete', $record) && self::isLatest($record))
                    ->action(function (Action $action, MeterReading $record): void {
                        DomainActions::forAction($action, fn () => app(DeleteMeterReading::class)->handle($record));
                    }),
            ])
            ->emptyStateIcon(Heroicon::OutlinedBolt)
            ->emptyStateHeading('Belum ada catatan meteran')
            ->emptyStateDescription('Catat angka meteran tiap kamar beserta fotonya. Catatan pertama kamar menjadi angka awal.');
    }

    /**
     * @return Builder<MeterReading>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = User::current();

        return MeterReading::query()->whereIn('room_id', Room::query()->accessibleBy($user)->select('rooms.id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMeterReadings::route('/'),
            'create' => RecordMeterReading::route('/catat'),
        ];
    }

    private static function isLatest(MeterReading $reading): bool
    {
        return ! MeterReading::query()
            ->where('room_id', $reading->room_id)
            ->where('utility', $reading->utility->value)
            ->whereDate('reading_date', '>', $reading->reading_date)
            ->exists();
    }

    private static function number(string $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
