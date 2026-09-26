<?php

namespace App\Modules\Property\Filament\App\Resources\Rooms\Schemas;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Filament\AttachmentUpload;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Room;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kamar')
                    ->columns(2)
                    ->schema([
                        Select::make('property_id')
                            ->label('Properti')
                            ->options(fn (): array => PropertyOptions::properties())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('room_type_id', null))
                            ->visibleOn('create'),
                        Select::make('room_type_id')
                            ->label('Tipe kamar')
                            ->options(fn (Get $get, ?Room $record): array => PropertyOptions::roomTypes(
                                $record->property_id ?? $get('property_id'),
                            ))
                            ->required(),
                        TextInput::make('number')
                            ->label('Nomor kamar')
                            ->required()
                            ->maxLength(20),
                        TextInput::make('floor')
                            ->label('Lantai')
                            ->maxLength(10),
                        TextInput::make('capacity')
                            ->label('Kapasitas')
                            ->helperText('Kosongkan untuk memakai kapasitas tipe kamar.')
                            ->suffix('orang')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(20),
                        TagsInput::make('facilities')
                            ->label('Fasilitas tambahan')
                            ->helperText('Di luar fasilitas tipe kamar.')
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ]),
                Section::make('Foto')
                    ->schema([
                        AttachmentUpload::make('photos', AttachmentCollection::Photo)
                            ->label('Foto kamar')
                            ->image()
                            ->maxFiles(10)
                            ->reorderable(),
                    ]),
            ]);
    }
}
