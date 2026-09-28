<?php

namespace App\Modules\Finance\Filament\App\Resources\Accounts\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Actions\CreateAccount;
use App\Modules\Finance\Filament\App\Resources\Accounts\AccountResource;
use App\Modules\Finance\Models\Account;
use App\Modules\Property\Filament\PropertyOptions;
use App\Support\Filament\DomainActions;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createAccount')
                ->label('Tambah akun')
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => User::current()->can('create', Account::class))
                ->modalHeading('Tambah akun')
                ->modalDescription('Tambahkan kategori pengeluaran atau pendapatan sendiri, atau kas terpisah per properti. Rekening bank ditambahkan lewat menu Rekening tujuan.')
                ->schema([
                    Radio::make('kind')
                        ->label('Jenis')
                        ->options(CreateAccount::KINDS)
                        ->default('expense')
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('code', $state === null ? null : CreateAccount::suggestCode($state))),
                    TextInput::make('name')->label('Nama')->placeholder('Misal: Beban keamanan')->required()->maxLength(100),
                    TextInput::make('code')
                        ->label('Kode')
                        ->default(fn (): string => CreateAccount::suggestCode('expense'))
                        ->required()
                        ->maxLength(20),
                    Select::make('property_id')
                        ->label('Khusus properti')
                        ->options(fn (): array => PropertyOptions::properties())
                        ->placeholder('Semua properti')
                        ->visible(fn (Get $get): bool => $get('kind') === 'cash'),
                ])
                ->modalSubmitActionLabel('Simpan akun')
                ->action(function (Action $action, array $data): void {
                    DomainActions::forAction($action, fn () => app(CreateAccount::class)->handle($data));

                    Notification::make()->success()->title('Akun ditambahkan')->send();
                }),
        ];
    }
}
