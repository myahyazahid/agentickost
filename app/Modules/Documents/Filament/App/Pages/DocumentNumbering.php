<?php

namespace App\Modules\Documents\Filament\App\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Actions\UpdateDocumentFormat;
use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Enums\ResetPeriod;
use App\Modules\Documents\Models\DocumentSequence;
use App\Modules\Documents\Support\DocumentNumbers;
use App\Modules\Property\Models\Property;
use App\Support\Filament\DomainActions;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Number formats of the tenant's documents (FR-BIL-07).
 */
class DocumentNumbering extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHashtag;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Penomoran dokumen';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'penomoran-dokumen';

    protected static ?string $title = 'Penomoran dokumen';

    public static function canAccess(): bool
    {
        return User::current()->can('update', DocumentSequence::class);
    }

    public function content(Schema $schema): Schema
    {
        $today = CarbonImmutable::now();
        $propertyCode = Property::query()->orderBy('created_at')->value('code');
        $numbers = app(DocumentNumbers::class);
        $sequences = DocumentSequence::query()->get()->keyBy(fn (DocumentSequence $sequence): string => $sequence->document_type->value);

        return $schema->components(array_map(function (DocumentType $type) use ($sequences, $numbers, $today, $propertyCode): Section {
            $sequence = $sequences->get($type->value);

            return Section::make($type->getLabel())
                ->key("numbering_{$type->value}")
                ->columns(['default' => 1, 'md' => 3])
                ->afterHeader([$this->editAction($type)])
                ->schema([
                    TextEntry::make("{$type->value}_format")
                        ->label('Format')
                        ->state($sequence->format ?? $type->defaultFormat()),
                    TextEntry::make("{$type->value}_reset")
                        ->label('Nomor urut')
                        ->state(($sequence->reset_period ?? $type->defaultResetPeriod())->getLabel()),
                    TextEntry::make("{$type->value}_next")
                        ->label('Nomor berikutnya')
                        ->weight('bold')
                        ->state($numbers->preview($type, $today, $propertyCode)),
                ]);
        }, DocumentType::cases()));
    }

    private function editAction(DocumentType $type): Action
    {
        return Action::make("edit_{$type->value}")
            ->label('Ubah format')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->link()
            ->modalHeading("Format nomor {$type->getLabel()}")
            ->modalSubmitActionLabel('Simpan')
            ->fillForm(function () use ($type): array {
                $sequence = DocumentSequence::query()->where('document_type', $type->value)->first();

                return [
                    'format' => $sequence->format ?? $type->defaultFormat(),
                    'reset_period' => ($sequence->reset_period ?? $type->defaultResetPeriod())->value,
                ];
            })
            ->schema([
                TextInput::make('format')
                    ->label('Format')
                    ->required()
                    ->maxLength(60)
                    ->helperText('Kode: {YYYY} tahun, {YY} tahun 2 digit, {MM} bulan, {PROP} kode properti, {SEQ:4} nomor urut 4 digit.'),
                Select::make('reset_period')
                    ->label('Nomor urut')
                    ->options(ResetPeriod::class)
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data, Action $action) use ($type): void {
                DomainActions::forAction($action, fn () => app(UpdateDocumentFormat::class)->handle($type, $data));

                Notification::make()->success()->title('Format nomor disimpan')->send();
            });
    }
}
