<?php

namespace App\Modules\Onboarding\Filament\App\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\BankAccountKind;
use App\Modules\Onboarding\Actions\SetUpProperty as SetUpPropertyAction;
use App\Modules\Onboarding\Enums\OnboardingPermission;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Filament\App\Resources\Properties\Schemas\PropertyForm;
use App\Modules\Property\Filament\App\Resources\Properties\Schemas\PropertySettingsForm;
use App\Modules\Property\Models\PropertySetting;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyInput;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The setup wizard (FR-ONB-01): property, billing rules, rooms with their
 * price, and transfer accounts, saved together at the last step.
 *
 * @property-read Schema $form
 */
class SetUpProperty extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Properti';

    protected static ?string $navigationLabel = 'Siapkan properti baru';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'siapkan-properti';

    protected static ?string $title = 'Siapkan properti baru';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return User::current()->can(OnboardingPermission::Manage->value);
    }

    public function getSubheading(): string
    {
        return 'Empat langkah untuk mulai menagih. Semua bisa diubah lagi nanti dari menu Properti.';
    }

    public function mount(): void
    {
        $defaults = array_map(
            fn (mixed $value): mixed => $value instanceof BackedEnum ? $value->value : $value,
            PropertySetting::defaults(),
        );

        $this->form->fill([
            ...$defaults,
            'room_types' => [['default_capacity' => 1, 'rental_period' => RentalPeriod::Monthly->value]],
            'bank_accounts' => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Properti')
                        ->description('Nama dan alamat')
                        ->schema([
                            PropertyForm::identitySection(),
                            PropertyForm::addressSection(),
                        ]),
                    Step::make('Tagihan dan denda')
                        ->description('Kapan menagih, kapan denda berlaku')
                        ->schema([
                            PropertySettingsForm::billingSection(),
                            PropertySettingsForm::penaltySection(),
                        ]),
                    Step::make('Kamar')
                        ->description('Tipe, harga, dan nomor kamar')
                        ->schema([
                            Repeater::make('room_types')
                                ->label('Tipe kamar')
                                ->addActionLabel('Tambah tipe kamar')
                                ->minItems(1)
                                ->columns(['default' => 1, 'md' => 3])
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                ->schema([
                                    TextInput::make('name')
                                        ->label('Nama tipe')
                                        ->placeholder('Misal: Standar')
                                        ->required()
                                        ->maxLength(80),
                                    TextInput::make('default_capacity')
                                        ->label('Kapasitas')
                                        ->suffix('orang')
                                        ->numeric()
                                        ->integer()
                                        ->minValue(1)
                                        ->maxValue(20)
                                        ->required(),
                                    Select::make('rental_period')
                                        ->label('Periode sewa')
                                        ->options(RentalPeriod::class)
                                        ->required(),
                                    MoneyInput::make('price')
                                        ->label('Harga per periode')
                                        ->required(),
                                    TagsInput::make('room_numbers')
                                        ->label('Nomor kamar')
                                        ->placeholder('Ketik nomor lalu tekan Enter, misal 101')
                                        ->helperText('Banyak kamar? Masukkan sebagian di sini, sisanya lewat Impor data.')
                                        ->required()
                                        ->columnSpan(['default' => 1, 'md' => 2]),
                                ]),
                        ]),
                    Step::make('Rekening')
                        ->description('Tujuan transfer penghuni')
                        ->schema([
                            Text::make('Rekening ini tampil di tagihan. Lewati bila penghuni selalu membayar tunai.'),
                            Repeater::make('bank_accounts')
                                ->hiddenLabel()
                                ->addActionLabel('Tambah rekening')
                                ->defaultItems(0)
                                ->columns(['default' => 1, 'md' => 2])
                                ->schema([
                                    Select::make('kind')
                                        ->label('Jenis')
                                        ->options(BankAccountKind::class)
                                        ->default(BankAccountKind::Bank->value)
                                        ->required(),
                                    TextInput::make('provider_name')
                                        ->label('Bank atau e-wallet')
                                        ->placeholder('Misal: BCA')
                                        ->required()
                                        ->maxLength(80),
                                    TextInput::make('account_number')
                                        ->label('Nomor rekening')
                                        ->required()
                                        ->maxLength(40),
                                    TextInput::make('account_holder')
                                        ->label('Atas nama')
                                        ->required()
                                        ->maxLength(100),
                                ]),
                        ]),
                ])
                    ->submitAction($this->getSubmitAction())
                    ->alpineSubmitHandler('$wire.create()')
                    ->contained(false),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    public function create(): void
    {
        $data = $this->form->getState();

        $property = DomainActions::forForm(fn () => app(SetUpPropertyAction::class)->handle($data));

        Notification::make()
            ->success()
            ->title("{$property->name} siap dipakai")
            ->body('Berikutnya: impor penghuni dan kontrak yang sedang berjalan.')
            ->send();

        $this->redirect(ImportData::getUrl());
    }

    protected function getSubmitAction(): Action
    {
        return Action::make('create')
            ->label('Simpan properti')
            ->action('create');
    }
}
