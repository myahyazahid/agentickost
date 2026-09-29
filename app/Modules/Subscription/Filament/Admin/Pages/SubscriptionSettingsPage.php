<?php

namespace App\Modules\Subscription\Filament\Admin\Pages;

use App\Modules\Subscription\Actions\UpdateBillingSettings;
use App\Modules\Subscription\Support\BillingSettings;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Filament\DomainActions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
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
 * Subscription rules for every tenant: grace and read-only periods (PRD
 * §9.6) and the transfer instructions shown on unpaid invoices.
 *
 * @property-read Schema $form
 */
class SubscriptionSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Langganan';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Pengaturan langganan';

    protected static ?string $slug = 'pengaturan-langganan';

    protected static ?string $title = 'Pengaturan langganan';

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
        $this->form->fill([
            'grace_days' => BillingSettings::graceDays(),
            'read_only_days' => BillingSettings::readOnlyDays(),
            'payment_instructions' => BillingSettings::paymentInstructions(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Langganan tidak dibayar')
                    ->description('Berlaku mulai proses harian berikutnya. Masa tenggang yang sudah berjalan tetap memakai tanggal akhirnya.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('grace_days')
                            ->label('Masa tenggang')
                            ->helperText('Data masih bisa diubah. Dihitung sejak hari terakhir periode yang dibayar.')
                            ->suffix('hari')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(60)
                            ->required(),
                        TextInput::make('read_only_days')
                            ->label('Masa baca saja')
                            ->helperText('Data bisa dilihat dan diekspor. Setelah itu login ditutup.')
                            ->suffix('hari')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required(),
                    ]),
                Section::make('Cara bayar')
                    ->description('Ditampilkan kepada owner di tagihan langganan yang belum dibayar.')
                    ->schema([
                        Textarea::make('payment_instructions')
                            ->hiddenLabel()
                            ->placeholder("Transfer ke BCA 1234567890 a.n. PT Contoh.\nKirim bukti ke WhatsApp 0812-0000-0000 dengan nomor tagihan.")
                            ->rows(4)
                            ->maxLength(2000),
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

        DomainActions::forForm(fn () => app(UpdateBillingSettings::class)->handle($data));

        Notification::make()->success()->title('Pengaturan tersimpan')->send();
    }
}
