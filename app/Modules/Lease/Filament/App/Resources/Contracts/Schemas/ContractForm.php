<?php

namespace App\Modules\Lease\Filament\App\Resources\Contracts\Schemas;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Actions\CreateResident;
use App\Modules\Lease\Enums\PayerRelation;
use App\Modules\Lease\Filament\App\Resources\Residents\Schemas\ResidentForm;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Room;
use App\Modules\Property\States\Room\Available;
use App\Modules\Property\States\Room\Booked;
use App\Modules\Property\Support\RoomPricing;
use App\Support\Filament\MoneyInput;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kamar')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('property_id')
                        ->label('Properti')
                        ->options(fn (): array => PropertyOptions::properties())
                        ->required()
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(fn (Set $set) => $set('room_id', null)),
                    Select::make('room_id')
                        ->label('Kamar')
                        ->helperText('Hanya kamar berstatus Tersedia atau Dipesan.')
                        ->options(fn (Get $get): array => self::roomOptions($get('property_id')))
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::fillRent($get, $set)),
                ]),
            Section::make('Penghuni')
                ->columnSpanFull()
                ->schema([
                    Select::make('resident_ids')
                        ->label('Penghuni')
                        ->helperText('Penghuni pertama menjadi penghuni utama. Belum terdaftar? Tekan tombol + untuk menambahkannya.')
                        ->multiple()
                        ->searchable()
                        ->options(fn (): array => self::residentOptions())
                        ->createOptionForm(ResidentForm::basicFields())
                        ->createOptionUsing(fn (array $data): string => app(CreateResident::class)->handle($data)->id)
                        ->required(),
                ]),
            Section::make('Pembayar')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Radio::make('payer')
                        ->label('Siapa yang membayar?')
                        ->options([
                            'self' => 'Penghuni utama',
                            'other' => 'Pihak lain, misal orang tua atau kantor',
                        ])
                        ->default('self')
                        ->required()
                        ->live()
                        ->columnSpanFull(),
                    ...self::otherPayerFields(),
                    Toggle::make('notify_resident')->label('Kirim tagihan ke penghuni')->default(true),
                ]),
            Section::make('Sewa')
                ->columnSpanFull()
                ->columns(2)
                ->schema(self::termsFields()),
            Section::make('Ketentuan tambahan')
                ->columnSpanFull()
                ->schema([
                    Textarea::make('clauses')->label('Ketentuan')->rows(4),
                ]),
        ]);
    }

    /**
     * @return list<Component>
     */
    public static function otherPayerFields(): array
    {
        $isOther = fn (Get $get): bool => $get('payer') === 'other';

        return [
            TextInput::make('payer_name')->label('Nama pembayar')->maxLength(150)->visible($isOther)->required($isOther),
            TextInput::make('payer_phone')->label('WhatsApp pembayar')->tel()->maxLength(20)->visible($isOther)->required($isOther),
            TextInput::make('payer_email')->label('Email pembayar')->email()->maxLength(150)->visible($isOther),
            Select::make('payer_relation')
                ->label('Hubungan dengan penghuni')
                ->options(PayerRelation::othersOptions())
                ->visible($isOther)
                ->required($isOther),
            Toggle::make('notify_payer')->label('Kirim tagihan ke pembayar')->default(true)->visible($isOther),
        ];
    }

    /**
     * Terms that can still change while the contract is a draft.
     *
     * @return list<Component>
     */
    public static function termsFields(): array
    {
        return [
            Select::make('rental_period')
                ->label('Periode bayar')
                ->options(RentalPeriod::class)
                ->default(RentalPeriod::Monthly->value)
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::fillRent($get, $set)),
            DatePicker::make('start_date')
                ->label('Mulai')
                ->default(now())
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::fillRent($get, $set)),
            DatePicker::make('end_date')
                ->label('Selesai')
                ->helperText('Kosongkan bila berjalan sampai salah satu pihak mengakhiri.'),
            MoneyInput::make('rent_amount')
                ->label('Sewa per periode')
                ->helperText('Dikunci di kontrak. Perubahan harga kamar nanti tidak memengaruhinya.')
                ->required(),
            MoneyInput::make('deposit_amount')
                ->label('Deposit')
                ->default(0)
                ->required(),
            MoneyInput::make('early_termination_penalty_amount')
                ->label('Denda pemutusan dini')
                ->helperText('Dikenakan bila kontrak diputus sebelum tanggal selesai.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function roomOptions(mixed $propertyId): array
    {
        if (! is_string($propertyId) || $propertyId === '') {
            return [];
        }

        return Room::query()
            ->accessibleBy(User::current())
            ->where('property_id', $propertyId)
            ->whereIn('status', [Available::$name, Booked::$name])
            ->with('roomType')
            ->orderBy('number')
            ->get()
            ->mapWithKeys(fn (Room $room): array => [$room->id => "Kamar {$room->number} ({$room->roomType?->name})"])
            ->all();
    }

    /**
     * Residents without a running contract. Flagged residents are marked so
     * staff see the warning before signing them (FR-PNH-06).
     *
     * @return array<string, string>
     */
    public static function residentOptions(): array
    {
        return Resident::query()
            ->accessibleBy(User::current())
            ->whereDoesntHave('contracts', fn (Builder $contracts) => $contracts
                ->whereIn('contracts.status', ContractState::runningValues())
                ->whereNull('contract_residents.left_on'))
            ->orderBy('full_name')
            ->get()
            ->mapWithKeys(fn (Resident $resident): array => [
                $resident->id => $resident->full_name.($resident->is_flagged ? ' (tidak disarankan)' : ''),
            ])
            ->all();
    }

    /**
     * Prefill the rent with the room's price for the chosen period and start
     * date, and the deposit with one period's rent when still empty.
     */
    private static function fillRent(Get $get, Set $set): void
    {
        $room = is_string($get('room_id')) ? Room::query()->whereKey($get('room_id'))->first() : null;
        $period = $get('rental_period');
        $period = $period instanceof RentalPeriod ? $period : RentalPeriod::tryFrom(is_string($period) ? $period : '');
        $start = $get('start_date');

        if ($room === null || $period === null || ! is_string($start) || $start === '') {
            return;
        }

        $price = app(RoomPricing::class)->priceFor($room, $period, CarbonImmutable::parse($start));

        if ($price === null) {
            return;
        }

        // Raw integers: a formatted "1.350.000" would be read as 1,35 by the
        // numeric state cast. The input mask adds the separators on screen.
        $set('rent_amount', $price);

        $deposit = $get('deposit_amount');

        if ($deposit === null || $deposit === '' || $deposit === '0' || $deposit === 0) {
            $set('deposit_amount', $price);
        }
    }
}
