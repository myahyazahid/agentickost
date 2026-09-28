<?php

namespace App\Modules\Finance\Filament\App\Resources\Expenses\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Filament\AttachmentUpload;
use App\Modules\Finance\Actions\RecordExpense as RecordExpenseAction;
use App\Modules\Finance\Filament\App\Resources\Expenses\ExpenseResource;
use App\Modules\Finance\Support\SpendingAccounts;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Property;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyInput;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Records money spent for a property, with the receipt (FR-ACC-03).
 */
class RecordExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected static ?string $title = 'Catat pengeluaran';

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('property_id')
                        ->label('Properti')
                        ->options(fn (): array => PropertyOptions::properties())
                        ->default(fn (): ?string => count($options = PropertyOptions::properties()) === 1 ? array_key_first($options) : null)
                        ->required(),
                    Select::make('expense_account_id')
                        ->label('Kategori')
                        ->options(fn (): array => SpendingAccounts::expenseAccounts()->pluck('name', 'id')->all())
                        ->required()
                        ->searchable(),
                    TextInput::make('description')
                        ->label('Keterangan')
                        ->placeholder('Misal: token listrik lorong 100 kWh')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    MoneyInput::make('amount')->label('Jumlah')->required()->minValue(1),
                    DatePicker::make('spent_on')->label('Tanggal')->default(now())->maxDate(now())->required(),
                    Select::make('paid_from_account_id')
                        ->label('Dibayar dari')
                        ->options(fn (): array => SpendingAccounts::paidFrom(User::current())->pluck('name', 'id')->all())
                        ->default(fn (): ?string => SpendingAccounts::paidFrom(User::current())->value('id'))
                        ->helperText('Bila dibayar dari kas yang Anda pegang, jumlah yang harus disetor berkurang.')
                        ->required()
                        ->native(false),
                    AttachmentUpload::make('receipts', AttachmentCollection::Document)
                        ->label('Nota atau kuitansi')
                        ->maxFiles(5)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $property = Property::query()->accessibleBy(User::current())->whereKey($data['property_id'] ?? null)->first();

        return DomainActions::forForm(function () use ($property, $data): Model {
            abort_if($property === null, 404);

            return app(RecordExpenseAction::class)->handle($property, $data);
        });
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan pengeluaran');
    }

    protected function getRedirectUrl(): string
    {
        return ExpenseResource::getUrl('index');
    }
}
