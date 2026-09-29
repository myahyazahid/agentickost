<?php

namespace App\Modules\Finance\Filament\App\Resources\Journals\Pages;

use App\Modules\Finance\Actions\PostManualJournal;
use App\Modules\Finance\Filament\App\Resources\Journals\JournalEntryResource;
use App\Modules\Finance\Models\Account;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Tenancy\TenantContext;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyInput;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Entering a manual journal (FR-ACC-05).
 */
class CreateManualJournal extends CreateRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected static ?string $title = 'Jurnal manual';

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    DatePicker::make('entry_date')
                        ->label('Tanggal')
                        ->default(fn (): string => CarbonImmutable::now(app(TenantContext::class)->tenant()->default_timezone)->toDateString())
                        ->required(),
                    Select::make('property_id')
                        ->label('Properti')
                        ->placeholder('Tanpa properti')
                        ->options(fn (): array => PropertyOptions::properties()),
                    TextInput::make('description')
                        ->label('Keterangan')
                        ->placeholder('Misal: biaya administrasi bank Oktober')
                        ->required()
                        ->maxLength(255),
                ]),
            Section::make('Baris jurnal')
                ->description('Total debit harus sama dengan total kredit. Piutang, deposit, saldo kredit, dan kas staf tidak bisa dipakai di sini.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('lines')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Akun')->width('36%'),
                            TableColumn::make('Debit')->width('18%'),
                            TableColumn::make('Kredit')->width('18%'),
                            TableColumn::make('Catatan'),
                        ])
                        ->schema([
                            Select::make('account_id')
                                ->options(fn (): array => self::accountOptions())
                                ->searchable()
                                ->required(),
                            MoneyInput::make('debit'),
                            MoneyInput::make('credit'),
                            TextInput::make('memo')->maxLength(255),
                        ])
                        ->defaultItems(2)
                        ->minItems(2)
                        ->addActionLabel('Tambah baris'),
                ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function accountOptions(): array
    {
        return Account::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('subtype')
                ->orWhereNotIn('subtype', array_map(fn ($subtype): string => $subtype->value, PostManualJournal::RESTRICTED_SUBTYPES)))
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $account): array => [$account->id => "{$account->code} {$account->name}"])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $lineKeys = array_keys(is_array($data['lines'] ?? null) ? $data['lines'] : []);

        try {
            return DomainActions::forForm(fn () => app(PostManualJournal::class)->handle($data));
        } catch (ValidationException $exception) {
            // The Action numbers lines from zero; the repeater keys its items by id.
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key): array => preg_match('/^data\.lines\.(\d+)\.(.+)$/', $key, $match) === 1 && isset($lineKeys[(int) $match[1]])
                    ? ["data.lines.{$lineKeys[(int) $match[1]]}.{$match[2]}" => $messages]
                    : [$key => $messages])
                ->all());
        }
    }

    protected function getRedirectUrl(): string
    {
        return JournalEntryResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
