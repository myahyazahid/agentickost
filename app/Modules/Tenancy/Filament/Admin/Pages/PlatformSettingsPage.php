<?php

namespace App\Modules\Tenancy\Filament\Admin\Pages;

use App\Modules\Tenancy\Actions\UpdatePlatformSettings;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\PlatformSettings;
use App\Support\Filament\DomainActions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Settings for the whole platform, starting with the trial length for new
 * tenants (FR-TNT-03).
 *
 * @property-read Schema $form
 */
class PlatformSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan platform';

    protected static ?string $slug = 'pengaturan';

    protected static ?string $title = 'Pengaturan platform';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $admin = Filament::auth()->user();

        return $admin !== null && $admin->can('manageSettings', Tenant::class);
    }

    public function mount(): void
    {
        $this->form->fill(['trial_days' => PlatformSettings::trialDays()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Trial')
                    ->description('Berlaku untuk tenant yang mendaftar sesudah disimpan. Akhir trial tenant yang sudah ada diubah dari halaman tenantnya.')
                    ->schema([
                        TextInput::make('trial_days')
                            ->label('Lama trial tenant baru')
                            ->suffix('hari')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required(),
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
                        Action::make('save')->label('Simpan pengaturan')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        DomainActions::forForm(fn () => app(UpdatePlatformSettings::class)->handle($data));

        Notification::make()->success()->title('Pengaturan tersimpan')->send();
    }
}
