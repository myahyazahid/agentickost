<?php

namespace App\Modules\Lease\Filament\App\Resources\Residents\Schemas;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Filament\AttachmentUpload;
use App\Modules\Lease\Enums\Gender;
use App\Modules\Lease\Enums\IdentityType;
use App\Modules\Lease\Enums\LeasePermission;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ResidentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data diri')
                ->columns(2)
                ->schema(self::basicFields()),
            Section::make('Identitas')
                ->description('Nomor dan foto identitas disimpan terenkripsi. Hanya owner dan manajer yang bisa melihatnya, dan setiap akses tercatat.')
                ->columns(2)
                ->schema([
                    Select::make('identity_type')
                        ->label('Jenis identitas')
                        ->options(IdentityType::class),
                    TextInput::make('identity_number')
                        ->label('Nomor identitas')
                        ->placeholder(fn (string $operation): ?string => $operation === 'edit' ? 'Tersimpan. Isi hanya untuk mengganti.' : null)
                        ->maxLength(50)
                        ->autocomplete(false),
                    AttachmentUpload::make('identity_documents', AttachmentCollection::Identity)
                        ->label(fn (string $operation): string => $operation === 'edit' ? 'Ganti foto identitas' : 'Foto identitas')
                        ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Kosongkan untuk tetap memakai foto yang tersimpan.' : null)
                        ->maxFiles(4)
                        ->columnSpanFull(),
                ]),
            Section::make('Kontak darurat')
                ->columns(3)
                ->schema([
                    TextInput::make('emergency_contact_name')->label('Nama')->maxLength(100),
                    TextInput::make('emergency_contact_phone')->label('Telepon')->tel()->maxLength(20),
                    TextInput::make('emergency_contact_relation')->label('Hubungan')->placeholder('Misal: ibu')->maxLength(40),
                ]),
            Section::make('Catatan internal')
                ->description('Hanya terlihat oleh staf kost ini, tidak oleh penghuni atau kost lain.')
                ->schema([
                    Textarea::make('internal_notes')->label('Catatan')->rows(3),
                    Toggle::make('is_flagged')
                        ->label('Tandai tidak disarankan')
                        ->helperText('Muncul sebagai peringatan saat penghuni ini akan dibuatkan kontrak baru.')
                        ->visible(fn (): bool => User::current()->can(LeasePermission::FlagResidents->value)),
                ]),
        ]);
    }

    /**
     * Fields also used to add a resident from the contract form.
     *
     * @return list<Component>
     */
    public static function basicFields(): array
    {
        return [
            TextInput::make('full_name')->label('Nama lengkap')->required()->maxLength(150),
            TextInput::make('phone')
                ->label('Nomor WhatsApp')
                ->helperText('Contoh: 0812 3456 7890')
                ->tel()
                ->required()
                ->maxLength(20),
            TextInput::make('email')->label('Email')->email()->maxLength(150),
            Select::make('gender')->label('Jenis kelamin')->options(Gender::class),
            DatePicker::make('birth_date')->label('Tanggal lahir')->maxDate(now()),
            TextInput::make('institution')->label('Kampus atau kantor')->maxLength(150),
            TextInput::make('vehicle_plate')->label('Pelat kendaraan')->maxLength(20),
        ];
    }
}
