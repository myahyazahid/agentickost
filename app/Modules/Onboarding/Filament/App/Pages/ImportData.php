<?php

namespace App\Modules\Onboarding\Filament\App\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Onboarding\Actions\ImportOnboardingData;
use App\Modules\Onboarding\Enums\OnboardingPermission;
use App\Modules\Onboarding\Import\ImportResult;
use App\Modules\Onboarding\Import\ImportSheet;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Property;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Imports rooms, residents, and running contracts from the template
 * (FR-ONB-02, FR-ONB-03). The owner checks the file first and sees every
 * row with its problems; the import button only works on a clean file.
 *
 * @property-read Schema $form
 *
 * @phpstan-import-type Sheet from ImportResult
 */
class ImportData extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Properti';

    protected static ?string $navigationLabel = 'Impor data';

    protected static ?int $navigationSort = 91;

    protected static ?string $slug = 'impor-data';

    protected static ?string $title = 'Impor kamar, penghuni, dan kontrak';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * The last check or import, as ImportResult::toArray().
     *
     * @var array{sheets: array<string, Sheet>, fileErrors: list<string>, committed: bool}|null
     */
    public ?array $result = null;

    public static function canAccess(): bool
    {
        return User::current()->can(OnboardingPermission::Manage->value);
    }

    public function getSubheading(): string
    {
        return 'Unduh template, isi di Excel, lalu unggah. Data baru disimpan bila semua baris benar.';
    }

    public function mount(): void
    {
        $options = PropertyOptions::properties();

        $this->form->fill([
            'property_id' => count($options) === 1 ? array_key_first($options) : null,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')
                ->label('Unduh template')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(route('onboarding.import-template')),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Select::make('property_id')
                            ->label('Properti')
                            ->options(fn (): array => PropertyOptions::properties())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->result = null),
                        Select::make('csv_sheet')
                            ->label('Isi berkas CSV')
                            ->helperText('Satu berkas CSV hanya memuat satu jenis data.')
                            ->options(ImportSheet::class)
                            ->visible(fn (Get $get): bool => self::isCsv($get('file')))
                            ->required(fn (Get $get): bool => self::isCsv($get('file')))
                            ->live()
                            ->afterStateUpdated(fn () => $this->result = null),
                        FileUpload::make('file')
                            ->label('Berkas')
                            ->helperText('Template .xlsx, atau .csv untuk satu jenis data.')
                            // By extension: .xlsx files often sniff as plain zip archives.
                            ->rules(['extensions:xlsx,csv'])
                            ->validationMessages(['extensions' => 'Pakai berkas .xlsx dari template, atau .csv.'])
                            ->maxSize(5120)
                            ->storeFiles(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->result = null)
                            ->columnSpanFull(),
                    ]),
                Actions::make([
                    Action::make('check')
                        ->label('Periksa berkas')
                        ->icon(Heroicon::OutlinedMagnifyingGlass)
                        ->color('gray')
                        ->action(fn () => $this->run(commit: false)),
                    Action::make('import')
                        ->label('Impor')
                        ->icon(Heroicon::OutlinedArrowUpTray)
                        ->disabled(fn (): bool => ! $this->isReadyToImport())
                        ->tooltip(fn (): ?string => $this->isReadyToImport() ? null : 'Periksa berkas dulu sampai tidak ada baris yang salah.')
                        ->requiresConfirmation()
                        ->modalHeading('Impor data ini?')
                        ->modalDescription(fn (): string => $this->confirmationText())
                        ->modalSubmitActionLabel('Impor')
                        ->action(fn () => $this->run(commit: true)),
                ])->key('importActions'),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('form'),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->rows())
            ->heading(fn (): ?string => $this->resultHeading())
            ->description(fn (): ?string => $this->result === null || $this->result['fileErrors'] === [] ? null : implode(' ', $this->result['fileErrors']))
            ->columns([
                TextColumn::make('sheet')->label('Data'),
                TextColumn::make('number')->label('Baris'),
                TextColumn::make('summary')->label('Isi')->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Diimpor' => 'success',
                        'Siap diimpor' => 'info',
                        default => 'danger',
                    }),
                TextColumn::make('errors')
                    ->label('Yang perlu diperbaiki')
                    ->listWithLineBreaks()
                    ->color('danger')
                    ->wrap(),
            ])
            ->paginated(false)
            ->emptyStateIcon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->emptyStateHeading(fn (): string => $this->result === null ? 'Belum ada berkas yang diperiksa' : 'Tidak ada baris yang terbaca')
            ->emptyStateDescription(fn (): string => $this->result === null
                ? 'Unggah berkas lalu tekan Periksa berkas. Setiap baris ditampilkan di sini beserta masalahnya, dan belum ada yang disimpan.'
                : 'Isi data mulai baris kedua di bawah judul kolom dari template, lalu unggah lagi.');
    }

    private function run(bool $commit): void
    {
        $data = $this->form->getState();
        $file = self::uploadedFile($data['file'] ?? null);
        $property = Property::query()->accessibleBy(User::current())->whereKey($data['property_id'] ?? null)->first();

        if ($file === null || $property === null) {
            throw ValidationException::withMessages(['data.file' => 'Pilih properti dan unggah berkasnya.']);
        }

        $result = app(ImportOnboardingData::class)->handle(
            $property,
            $file->getRealPath(),
            // The stored name keeps the uploaded extension; the original name
            // is not always recoverable from a temporary upload.
            $file->getFilename(),
            $commit,
            ImportSheet::tryFrom((string) ($data['csv_sheet'] ?? '')),
        );

        $this->result = $result->toArray();

        match (true) {
            $result->committed => Notification::make()->success()->title('Data diimpor')
                ->body("{$result->rowCount(ImportSheet::Rooms)} kamar, {$result->rowCount(ImportSheet::Residents)} penghuni, dan {$result->rowCount(ImportSheet::Contracts)} kontrak tersimpan. Berikutnya: catat tunggakan dan deposit di Saldo awal.")
                ->send(),
            $result->isClean() => Notification::make()->success()->title('Semua baris benar')->body('Tekan Impor untuk menyimpan.')->send(),
            default => Notification::make()->danger()->title('Ada yang perlu diperbaiki')
                ->body($result->fileErrors() !== [] ? implode(' ', $result->fileErrors()) : "{$result->errorCount()} baris belum benar. Tidak ada data yang disimpan.")
                ->send(),
        };

        if ($result->committed) {
            $this->form->fill(['property_id' => $property->id]);
        }
    }

    private function isReadyToImport(): bool
    {
        return $this->result !== null && ! $this->result['committed'] && ImportResult::fromArray($this->result)->isClean();
    }

    private function confirmationText(): string
    {
        if ($this->result === null) {
            return '';
        }

        $result = ImportResult::fromArray($this->result);

        return "{$result->rowCount(ImportSheet::Rooms)} kamar, {$result->rowCount(ImportSheet::Residents)} penghuni, dan {$result->rowCount(ImportSheet::Contracts)} kontrak akan disimpan. Kontrak langsung aktif dan kamarnya terisi.";
    }

    private function resultHeading(): ?string
    {
        if ($this->result === null) {
            return null;
        }

        $result = ImportResult::fromArray($this->result);

        return match (true) {
            $result->committed => "Diimpor: {$result->rowCount()} baris",
            $result->errorCount() > 0 => "{$result->errorCount()} dari {$result->rowCount()} baris perlu diperbaiki",
            default => "{$result->rowCount()} baris siap diimpor",
        };
    }

    /**
     * @return array<string, array{sheet: string, number: int, summary: string, status: string, errors: list<string>}>
     */
    private function rows(): array
    {
        if ($this->result === null) {
            return [];
        }

        $rows = [];

        foreach ($this->result['sheets'] as $sheet) {
            foreach ($sheet['rows'] as $row) {
                $rows["{$sheet['sheet']}-{$row['number']}"] = [
                    'sheet' => $sheet['label'],
                    'number' => $row['number'],
                    'summary' => $row['summary'],
                    'status' => match (true) {
                        $row['errors'] !== [] => 'Perlu diperbaiki',
                        $this->result['committed'] => 'Diimpor',
                        default => 'Siap diimpor',
                    },
                    'errors' => $row['errors'],
                ];
            }
        }

        return $rows;
    }

    private static function uploadedFile(mixed $state): ?TemporaryUploadedFile
    {
        if (is_array($state)) {
            $state = reset($state);
        }

        return $state instanceof TemporaryUploadedFile ? $state : null;
    }

    private static function isCsv(mixed $state): bool
    {
        return strtolower(pathinfo((string) self::uploadedFile($state)?->getFilename(), PATHINFO_EXTENSION)) === 'csv';
    }
}
