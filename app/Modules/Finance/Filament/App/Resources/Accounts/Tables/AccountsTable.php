<?php

namespace App\Modules\Finance\Filament\App\Resources\Accounts\Tables;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Actions\UpdateAccount;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Filament\App\Resources\Accounts\AccountResource;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Support\LedgerBalances;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyColumn;
use App\Support\Money\Rupiah;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn () => LedgerBalances::withTotals())
            ->description(fn (): string => self::trialBalance())
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable(),
                TextColumn::make('name')
                    ->label('Akun')
                    ->description(fn (Account $record): string => $record->type->getLabel().($record->is_active ? '' : ', nonaktif'))
                    ->searchable(),
                MoneyColumn::make('debit_total')->label('Debit'),
                MoneyColumn::make('credit_total')->label('Kredit'),
                MoneyColumn::make('balance')
                    ->label('Saldo')
                    ->state(fn (Account $record): int => self::balance($record))
                    ->weight('bold'),
            ])
            ->defaultSort('code')
            ->paginated(false)
            ->filters([
                SelectFilter::make('type')->label('Golongan')->options(AccountType::class),
            ])
            ->recordUrl(fn (Account $record): string => AccountResource::getUrl('ledger', ['record' => $record]))
            ->recordActions([
                Action::make('edit')
                    ->label('Ubah')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (Account $record): bool => User::current()->can('update', $record))
                    ->fillForm(fn (Account $record): array => ['name' => $record->name, 'is_active' => $record->is_active])
                    ->schema([
                        TextInput::make('name')->label('Nama')->required()->maxLength(100),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->helperText('Akun nonaktif tidak lagi ditawarkan saat mencatat transaksi.')
                            ->visible(fn (Account $record): bool => ! $record->is_system),
                    ])
                    ->action(function (Action $action, Account $record, array $data): void {
                        DomainActions::forAction($action, fn () => app(UpdateAccount::class)->handle($record, $data));
                    }),
            ])
            ->emptyStateHeading('Bagan akun belum dibuat')
            ->emptyStateDescription('Akun bawaan dibuat otomatis saat tenant terdaftar.');
    }

    public static function balance(Account $account): int
    {
        $net = (int) $account->getAttribute('debit_total') - (int) $account->getAttribute('credit_total');

        return $account->type->isDebitNormal() ? $net : -$net;
    }

    private static function trialBalance(): string
    {
        ['debit' => $debit, 'credit' => $credit] = LedgerBalances::trialTotals();

        return $debit === $credit
            ? 'Neraca saldo seimbang: total debit dan kredit sama-sama '.Rupiah::format($debit).'.'
            : 'Neraca saldo tidak seimbang: debit '.Rupiah::format($debit).', kredit '.Rupiah::format($credit).'.';
    }
}
