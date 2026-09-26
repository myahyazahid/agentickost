<?php

namespace App\Modules\Property\Filament\App\Resources\Properties\Schemas;

use App\Modules\Property\Enums\AllocationCategory;
use App\Modules\Property\Enums\BillingMode;
use App\Modules\Property\Enums\PenaltyType;
use App\Modules\Property\Enums\ProrationBasis;
use App\Support\Filament\MoneyInput;
use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PropertySettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Siklus tagihan')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('billing_mode')
                            ->label('Jatuh tempo')
                            ->options(BillingMode::class)
                            ->required()
                            ->live(),
                        TextInput::make('fixed_billing_day')
                            ->label('Tanggal jatuh tempo setiap bulan')
                            ->helperText('1 sampai 28, agar ada di setiap bulan. Bulan pertama penghuni diprorata.')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(28)
                            ->visible(fn (Get $get): bool => self::is($get('billing_mode'), BillingMode::FixedDate))
                            ->required(fn (Get $get): bool => self::is($get('billing_mode'), BillingMode::FixedDate)),
                        TextInput::make('invoice_lead_days')
                            ->label('Tagihan terbit sebelum jatuh tempo')
                            ->suffix('hari')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(60)
                            ->required(),
                        Select::make('proration_basis')
                            ->label('Dasar hitung prorata')
                            ->options(ProrationBasis::class)
                            ->required(),
                        Select::make('rounding_unit')
                            ->label('Pembulatan total tagihan')
                            ->helperText('Selisih pembulatan dicatat sebagai baris tersendiri di tagihan.')
                            ->options([
                                1 => 'Tanpa pembulatan',
                                100 => 'Ke kelipatan Rp100',
                                500 => 'Ke kelipatan Rp500',
                                1000 => 'Ke kelipatan Rp1.000',
                            ])
                            ->required(),
                    ]),
                Section::make('Denda keterlambatan')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('grace_days')
                            ->label('Masa tenggang setelah jatuh tempo')
                            ->suffix('hari')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(60)
                            ->required(),
                        Select::make('penalty_type')
                            ->label('Jenis denda')
                            ->options(PenaltyType::class)
                            ->required()
                            ->live(),
                        MoneyInput::make('penalty_amount')
                            ->label(fn (Get $get): string => self::is($get('penalty_type'), PenaltyType::Daily) ? 'Denda per hari' : 'Nominal denda')
                            ->visible(fn (Get $get): bool => self::is($get('penalty_type'), PenaltyType::Flat, PenaltyType::Daily))
                            ->required(fn (Get $get): bool => self::is($get('penalty_type'), PenaltyType::Flat, PenaltyType::Daily)),
                        TextInput::make('penalty_percent')
                            ->label('Persen dari sisa tagihan')
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(100)
                            ->visible(fn (Get $get): bool => self::is($get('penalty_type'), PenaltyType::Percent))
                            ->required(fn (Get $get): bool => self::is($get('penalty_type'), PenaltyType::Percent)),
                        MoneyInput::make('penalty_max_amount')
                            ->label('Batas denda per tagihan')
                            ->helperText('Kosongkan bila tanpa batas.')
                            ->visible(fn (Get $get): bool => ! self::is($get('penalty_type'), PenaltyType::None)),
                    ]),
                Section::make('Urutan pelunasan')
                    ->columnSpanFull()
                    ->description('Pembayaran melunasi tagihan tertua lebih dulu. Di dalam satu tagihan, komponen dilunasi dari atas ke bawah. Geser atau pakai tombol panah untuk mengubah urutan.')
                    ->schema([
                        Repeater::make('allocation_order')
                            ->hiddenLabel()
                            ->schema([
                                Hidden::make('category'),
                                Text::make(fn (Get $get): ?string => AllocationCategory::tryFrom((string) $get('category'))?->description()),
                            ])
                            ->itemLabel(fn (array $state): ?string => AllocationCategory::tryFrom((string) ($state['category'] ?? ''))?->getLabel())
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->addable(false)
                            ->deletable(false),
                    ]),
                Section::make('Penghuni keluar')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('notice_days')
                            ->label('Pemberitahuan keluar paling lambat')
                            ->suffix('hari sebelum tanggal keluar')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(365)
                            ->required(),
                    ]),
            ]);
    }

    private static function is(mixed $state, BackedEnum ...$cases): bool
    {
        foreach ($cases as $case) {
            if ($state === $case || $state === $case->value) {
                return true;
            }
        }

        return false;
    }
}
