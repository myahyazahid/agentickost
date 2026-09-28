<?php

namespace App\Modules\Payment\Filament\App\Resources\Payments\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Filament\App\Resources\Invoices\Schemas\AdhocInvoiceForm;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Filament\AttachmentUpload;
use App\Modules\Payment\Actions\RecordPayment as RecordPaymentAction;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Filament\App\Resources\Payments\PaymentResource;
use App\Modules\Payment\Filament\PaymentFields;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\States\Payment\Pending;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Property;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyInput;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Records money received for a contract (FR-PAY-01). Opened from an invoice,
 * the contract is filled in already.
 */
class RecordPayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected static ?string $title = 'Catat pembayaran';

    protected static bool $canCreateAnother = false;

    #[Url(as: 'kontrak')]
    public ?string $contractFromUrl = null;

    public function form(Schema $schema): Schema
    {
        $preset = PaymentFields::contract($this->contractFromUrl);

        return $schema->components([
            Section::make('Pembayaran untuk')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('property_id')
                        ->label('Properti')
                        ->options(fn (): array => PropertyOptions::properties())
                        ->default(fn (): ?string => $preset->property_id
                            ?? (count($options = PropertyOptions::properties()) === 1 ? array_key_first($options) : null))
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('contract_id', null);
                            $set('bank_account_id', PaymentFields::defaultBankAccount($state));
                        })
                        ->dehydrated(false),
                    Select::make('contract_id')
                        ->label('Kontrak')
                        ->options(fn (Get $get): array => AdhocInvoiceForm::contractOptions($get('property_id')))
                        ->default($preset?->id)
                        ->searchable()
                        ->required()
                        ->live(),
                    Text::make(fn (Get $get): string => PaymentFields::contractSummary($get('contract_id')))
                        ->columnSpanFull(),
                ]),
            Section::make('Uang yang diterima')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Radio::make('method')
                        ->label('Cara bayar')
                        ->options(PaymentMethod::manualOptions())
                        ->default(PaymentMethod::Transfer->value)
                        ->inline()
                        ->required()
                        ->live()
                        ->columnSpanFull(),
                    MoneyInput::make('amount')
                        ->label('Jumlah')
                        ->required()
                        ->minValue(1),
                    DateTimePicker::make('paid_at')
                        ->label('Waktu bayar')
                        ->helperText('Sesuai bukti transfer atau saat uang diterima.')
                        ->timezone(fn (Get $get): string => self::timezone($get('property_id')))
                        ->seconds(false)
                        ->default(now())
                        ->maxDate(now()->endOfDay())
                        ->required(),
                    Select::make('bank_account_id')
                        ->label('Rekening tujuan')
                        ->options(fn (Get $get): array => PaymentFields::bankAccountOptions($get('property_id')))
                        ->default(fn (Get $get): ?string => PaymentFields::defaultBankAccount($get('property_id')))
                        ->visible(fn (Get $get): bool => $get('method') === PaymentMethod::Transfer->value)
                        ->required(fn (Get $get): bool => $get('method') === PaymentMethod::Transfer->value)
                        ->native(false),
                    TextInput::make('reference')
                        ->label('Nomor referensi atau berita transfer')
                        ->maxLength(100)
                        ->visible(fn (Get $get): bool => $get('method') === PaymentMethod::Transfer->value),
                    Select::make('received_by_user_id')
                        ->label('Diterima oleh')
                        ->options(fn (Get $get): array => PaymentFields::receiverOptions($get('property_id'), $this->canVerify($get('property_id'))))
                        ->default(fn (): string => User::current()->id)
                        ->helperText('Uang tunai yang diterima staf dicatat sebagai kas di tangannya sampai disetor.')
                        ->visible(fn (Get $get): bool => $get('method') === PaymentMethod::Cash->value)
                        ->required(fn (Get $get): bool => $get('method') === PaymentMethod::Cash->value)
                        ->native(false),
                    AttachmentUpload::make('proofs', AttachmentCollection::PaymentProof)
                        ->label('Bukti bayar')
                        ->image()
                        ->maxFiles(5)
                        ->columnSpanFull(),
                    Text::make('Transfer yang Anda catat menunggu verifikasi manajer atau owner sebelum melunasi tagihan.')
                        ->visible(fn (Get $get): bool => $get('method') === PaymentMethod::Transfer->value && ! $this->canVerify($get('property_id')))
                        ->columnSpanFull(),
                ]),
            Section::make('Alokasi ke tagihan')
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => $this->canVerify($get('property_id')))
                ->schema(PaymentFields::allocation(fn (Get $get): ?string => $get('../../contract_id'))),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $contract = PaymentFields::contract($data['contract_id'] ?? null);

        return DomainActions::forForm(function () use ($contract, $data): Model {
            abort_if($contract === null, 404);

            $allocation = PaymentFields::allocationInput($data);
            unset($data['allocations'], $data['allocation_mode']);

            return app(RecordPaymentAction::class)->handle($contract, [...$data, ...$allocation]);
        });
    }

    protected function getCreatedNotification(): ?Notification
    {
        $payment = $this->getRecord();

        return Notification::make()
            ->success()
            ->title($payment instanceof Payment && $payment->status->equals(Pending::class)
                ? 'Pembayaran masuk antrian verifikasi'
                : 'Pembayaran dicatat dan tagihan diperbarui');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan pembayaran');
    }

    protected function getRedirectUrl(): string
    {
        return PaymentResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    private function canVerify(?string $propertyId): bool
    {
        $property = Property::query()->find($propertyId);

        return $property !== null && User::current()->can('verifyIn', [Payment::class, $property]);
    }

    private static function timezone(?string $propertyId): string
    {
        return Property::query()->find($propertyId)?->timezone->value ?? (string) config('app.timezone');
    }
}
