<?php

namespace App\Modules\Property\Filament\App\Resources\Properties\Schemas;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Filament\AttachmentUpload;
use App\Modules\Property\Enums\GenderPolicy;
use App\Support\Timezone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::identitySection(),
                self::addressSection(),
                Section::make('Aturan dan fasilitas')
                    ->schema([
                        Textarea::make('rules')
                            ->label('Aturan kost')
                            ->rows(4),
                        TagsInput::make('facilities')
                            ->label('Fasilitas umum')
                            ->placeholder('Ketik lalu tekan Enter, misal Wi-Fi'),
                    ]),
                Section::make('Foto')
                    ->schema([
                        AttachmentUpload::make('photos', AttachmentCollection::Photo)
                            ->label('Foto properti')
                            ->image()
                            ->maxFiles(10)
                            ->reorderable(),
                    ]),
            ]);
    }

    /**
     * Also used by the setup wizard (FR-ONB-01).
     */
    public static function identitySection(): Section
    {
        return Section::make('Identitas')
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label('Nama properti')
                    ->required()
                    ->maxLength(150),
                TextInput::make('code')
                    ->label('Kode')
                    ->helperText('Singkatan untuk nomor dokumen, misal KMG.')
                    ->required()
                    ->maxLength(20),
                Select::make('gender_policy')
                    ->label('Jenis kost')
                    ->options(GenderPolicy::class)
                    ->required(),
                Select::make('timezone')
                    ->label('Zona waktu')
                    ->helperText('Jatuh tempo dan denda dihitung menurut zona waktu ini.')
                    ->options(Timezone::class)
                    ->default(Timezone::Wib->value)
                    ->required(),
            ]);
    }

    public static function addressSection(): Section
    {
        return Section::make('Alamat')
            ->columns(2)
            ->schema([
                Textarea::make('address')
                    ->label('Alamat')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('city')
                    ->label('Kota atau kabupaten')
                    ->required()
                    ->maxLength(80),
                TextInput::make('province')
                    ->label('Provinsi')
                    ->required()
                    ->maxLength(80),
                TextInput::make('postal_code')
                    ->label('Kode pos')
                    ->maxLength(10),
            ]);
    }
}
