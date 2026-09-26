<?php

namespace App\Modules\Billing\Filament\App\Resources\Invoices\Schemas;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Enums\InvoiceItemType;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\Draft;
use App\Modules\Property\Filament\PropertyOptions;
use App\Support\Filament\MoneyInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * A manual invoice (FR-BIL-05). Rent is billed automatically, so this is for
 * charges such as damage, laundry, or a one-off fee.
 */
class AdhocInvoiceForm
{
    public static function configure(Schema $schema, bool $creating): Schema
    {
        return $schema->components([
            Section::make('Tagihan untuk')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->visible($creating)
                ->schema([
                    Select::make('property_id')
                        ->label('Properti')
                        ->options(fn (): array => PropertyOptions::properties())
                        ->default(fn (): ?string => count($options = PropertyOptions::properties()) === 1 ? array_key_first($options) : null)
                        ->required($creating)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('contract_id', null)),
                    Select::make('contract_id')
                        ->label('Kontrak')
                        ->helperText('Tagihan dikirim ke pembayar kontrak ini.')
                        ->options(fn (Get $get): array => self::contractOptions($get('property_id')))
                        ->searchable()
                        ->required($creating),
                ]),
            Section::make('Rincian')
                ->columnSpanFull()
                ->schema([
                    DatePicker::make('due_date')
                        ->label('Jatuh tempo')
                        ->default(now()->addDays(7))
                        ->required(),
                    Repeater::make('items')
                        ->label('Baris tagihan')
                        ->table([
                            TableColumn::make('Jenis')->width('28%'),
                            TableColumn::make('Keterangan'),
                            TableColumn::make('Jumlah')->width('24%'),
                        ])
                        ->schema([
                            Select::make('type')
                                ->options(InvoiceItemType::manualOptions())
                                ->default(InvoiceItemType::Damage->value)
                                ->required()
                                ->native(false),
                            TextInput::make('description')->required()->maxLength(255),
                            MoneyInput::make('amount')->required()->minValue(1),
                        ])
                        ->defaultItems(1)
                        ->minItems(1)
                        ->addActionLabel('Tambah baris')
                        ->helperText('Diskon diisi sebagai angka positif; sistem menguranginya dari total.'),
                    Toggle::make('issue_now')
                        ->label('Langsung terbitkan')
                        ->helperText('Bila tidak dicentang, tagihan disimpan sebagai draf dan masih bisa diubah.')
                        ->visible($creating),
                ]),
        ]);
    }

    /**
     * Contracts that can still be billed: running ones, and ended ones for a
     * last charge such as damage found at check-out.
     *
     * @return array<string, string>
     */
    public static function contractOptions(?string $propertyId): array
    {
        if ($propertyId === null) {
            return [];
        }

        return Contract::query()
            ->accessibleBy(User::current())
            ->where('property_id', $propertyId)
            ->whereNot('status', Draft::$name)
            ->with(['room', 'payer'])
            ->orderByDesc('start_date')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (Contract $contract): array => [
                $contract->id => "Kamar {$contract->room?->number}, {$contract->payer?->name} ({$contract->number}, {$contract->status->getLabel()})",
            ])
            ->all();
    }
}
