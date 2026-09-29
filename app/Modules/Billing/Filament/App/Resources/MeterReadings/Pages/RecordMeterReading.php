<?php

namespace App\Modules\Billing\Filament\App\Resources\MeterReadings\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Actions\RecordMeterReading as RecordMeterReadingAction;
use App\Modules\Billing\Enums\UtilityKind;
use App\Modules\Billing\Enums\UtilityMode;
use App\Modules\Billing\Filament\App\Resources\MeterReadings\MeterReadingResource;
use App\Modules\Billing\Models\MeterReading;
use App\Modules\Billing\Models\UtilityRate;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Filament\AttachmentUpload;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Room;
use App\Support\Filament\DomainActions;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The caretaker's form, used on a phone while walking from room to room.
 */
class RecordMeterReading extends CreateRecord
{
    protected static string $resource = MeterReadingResource::class;

    protected static ?string $title = 'Catat meteran';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('property_id')
                        ->label('Properti')
                        ->options(fn (): array => PropertyOptions::properties())
                        ->default(fn (): ?string => count($options = PropertyOptions::properties()) === 1 ? array_key_first($options) : null)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('room_id', null))
                        ->dehydrated(false),
                    Select::make('room_id')
                        ->label('Kamar')
                        ->options(fn (Get $get): array => self::roomOptions($get('property_id')))
                        ->searchable()
                        ->required()
                        ->live(),
                    Select::make('utility')
                        ->label('Utilitas')
                        ->options(fn (Get $get): array => self::meteredUtilities($get('property_id'), $get('reading_date')))
                        ->default(UtilityKind::Electricity->value)
                        ->required()
                        ->live(),
                    DatePicker::make('reading_date')
                        ->label('Tanggal catat')
                        ->default(now())
                        ->maxDate(now())
                        ->required()
                        ->live(),
                    Text::make(fn (Get $get): string => self::lastReadingHint($get('room_id'), $get('utility')))
                        ->columnSpanFull(),
                    TextInput::make('current_value')
                        ->label('Angka meteran sekarang')
                        ->numeric()
                        ->inputMode('decimal')
                        ->step(0.01)
                        ->minValue(0)
                        ->required(),
                    Toggle::make('is_meter_replaced')
                        ->label('Meteran baru diganti')
                        ->helperText('Nyalakan bila meteran diganti sejak catatan terakhir.')
                        ->live(),
                    TextInput::make('new_meter_start_value')
                        ->label('Angka awal meteran baru')
                        ->numeric()
                        ->inputMode('decimal')
                        ->default(0)
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => (bool) $get('is_meter_replaced')),
                    AttachmentUpload::make('photos', AttachmentCollection::Meter)
                        ->label('Foto meteran')
                        ->maxFiles(3)
                        ->required()
                        ->columnSpanFull(),
                    Hidden::make('client_uuid')->default(fn (): string => (string) Str::uuid()),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $room = Room::query()->accessibleBy(User::current())->whereKey($data['room_id'] ?? null)->firstOrFail();

        return DomainActions::forForm(fn (): MeterReading => app(RecordMeterReadingAction::class)->handle($room, [
            ...$data,
            'photos' => array_values((array) ($data['photos'] ?? [])),
        ]));
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan');
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->label('Simpan & catat kamar lain');
    }

    protected function getRedirectUrl(): string
    {
        return MeterReadingResource::getUrl('index');
    }

    /**
     * Keep the property, utility, and date for the next room.
     */
    protected function preserveFormDataWhenCreatingAnother(array $data): array
    {
        return [
            'property_id' => $data['property_id'] ?? null,
            'utility' => $data['utility'] ?? null,
            'reading_date' => $data['reading_date'] ?? null,
            'client_uuid' => (string) Str::uuid(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function roomOptions(?string $propertyId): array
    {
        if ($propertyId === null) {
            return [];
        }

        return Room::query()
            ->accessibleBy(User::current())
            ->where('property_id', $propertyId)
            ->orderBy('number')
            ->pluck('number', 'id')
            ->map(fn (string $number): string => "Kamar {$number}")
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function meteredUtilities(?string $propertyId, mixed $date): array
    {
        if ($propertyId === null) {
            return [];
        }

        $on = CarbonImmutable::parse(is_string($date) && $date !== '' ? $date : now());
        $options = [];

        foreach (UtilityKind::cases() as $utility) {
            if (UtilityRate::inForce($propertyId, $utility, $on)?->mode === UtilityMode::Metered) {
                $options[$utility->value] = $utility->getLabel();
            }
        }

        return $options;
    }

    private static function lastReadingHint(?string $roomId, mixed $utility): string
    {
        if ($roomId === null || ! is_string($utility) || $utility === '') {
            return 'Pilih kamar untuk melihat catatan terakhirnya.';
        }

        $last = MeterReading::query()
            ->whereIn('room_id', Room::query()->accessibleBy(User::current())->whereKey($roomId)->select('id'))
            ->where('utility', $utility)
            ->latest('reading_date')
            ->first();

        return $last === null
            ? 'Kamar ini belum punya catatan. Angka yang dicatat sekarang menjadi angka awal dan tidak ditagihkan.'
            : 'Catatan terakhir: '.number_format((float) $last->current_value, 2, ',', '.').' pada '.$last->reading_date->translatedFormat('j M Y').'.';
    }
}
