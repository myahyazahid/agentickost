<?php

namespace App\Modules\Tenancy\Filament\App\Pages;

use App\Modules\Tenancy\Actions\UpdateTenantProfile;
use App\Modules\Tenancy\Support\TenantBranding;
use App\Modules\Tenancy\Support\TenantStorage;
use App\Modules\Tenancy\TenantContext;
use App\Support\Filament\DomainActions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The business name, logo, and accent colour on invoices, receipts, and
 * contracts (FR-SUB-07).
 *
 * @property-read Schema $form
 */
class BusinessProfile extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Properti';

    protected static ?string $navigationLabel = 'Profil usaha';

    protected static ?int $navigationSort = 85;

    protected static ?string $slug = 'profil-usaha';

    protected static ?string $title = 'Profil usaha';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $tenants = app(TenantContext::class);
        $user = Filament::auth()->user();

        return $tenants->has() && $user !== null && $user->can('updateProfile', $tenants->tenant());
    }

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->tenant();

        $this->form->fill([
            'name' => $tenant->name,
            'logo_path' => $tenant->logo_path,
            'brand_color' => $tenant->brand_color,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description('Tampil di tagihan, kuitansi, dan kontrak yang dikirim ke penghuni.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama usaha')
                            ->required()
                            ->maxLength(150),
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->helperText('PNG atau JPG, paling besar '.TenantBranding::LOGO_MAX_KB.' KB. Logo lebar lebih mudah dibaca di dokumen.')
                            ->image()
                            ->disk(fn (): string => (string) config('filesystems.default'))
                            ->directory(fn (): string => app(TenantStorage::class)->path(TenantBranding::LOGO_DIRECTORY))
                            ->visibility('private')
                            ->maxSize(TenantBranding::LOGO_MAX_KB)
                            ->acceptedFileTypes(TenantBranding::LOGO_TYPES)
                            ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => $file === app(TenantContext::class)->tenant()->logo_path),
                        ColorPicker::make('brand_color')
                            ->label('Warna aksen')
                            ->helperText('Dipakai untuk garis dan judul di dokumen. Kosongkan untuk warna bawaan.')
                            ->hex()
                            ->regex('/^#[0-9a-fA-F]{6}$/'),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Simpan profil')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        DomainActions::forForm(fn () => app(UpdateTenantProfile::class)->handle($data));

        Notification::make()->success()->title('Profil usaha tersimpan')->send();
    }
}
