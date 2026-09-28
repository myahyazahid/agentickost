<?php

namespace App\Modules\Finance\Filament\App\Resources\Accounts;

use App\Modules\Finance\Filament\App\Resources\Accounts\Pages\AccountLedger;
use App\Modules\Finance\Filament\App\Resources\Accounts\Pages\ListAccounts;
use App\Modules\Finance\Filament\App\Resources\Accounts\Tables\AccountsTable;
use App\Modules\Finance\Models\Account;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Chart of accounts with the totals of each account's ledger (FR-ACC-01).
 */
class AccountResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Bagan akun';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'akun';

    protected static ?string $pluralModelLabel = 'bagan akun';

    protected static ?string $slug = 'akun';

    public static function table(Table $table): Table
    {
        return AccountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccounts::route('/'),
            'ledger' => AccountLedger::route('/{record}/buku-besar'),
        ];
    }
}
