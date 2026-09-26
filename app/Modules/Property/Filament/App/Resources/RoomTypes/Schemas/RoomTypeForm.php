<?php

namespace App\Modules\Property\Filament\App\Resources\RoomTypes\Schemas;

use App\Modules\Property\Filament\PropertyOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoomTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('property_id')
                            ->label('Properti')
                            ->options(fn (): array => PropertyOptions::properties())
                            ->required()
                            ->visibleOn('create'),
                        TextInput::make('name')
                            ->label('Nama tipe')
                            ->placeholder('Misal: AC kamar mandi dalam')
                            ->required()
                            ->maxLength(80),
                        TextInput::make('default_capacity')
                            ->label('Kapasitas')
                            ->suffix('orang')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(1)
                            ->required(),
                        Textarea::make('description')
                            ->label('Keterangan')
                            ->columnSpanFull(),
                        TagsInput::make('facilities')
                            ->label('Fasilitas kamar')
                            ->placeholder('Ketik lalu tekan Enter, misal AC')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
