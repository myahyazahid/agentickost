<?php

namespace App\Modules\Lease\Filament\App\Resources\Contracts;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Filament\App\RelationManagers\DepositTransactionsRelationManager;
use App\Modules\Lease\Filament\App\Resources\Contracts\Pages\CreateContract;
use App\Modules\Lease\Filament\App\Resources\Contracts\Pages\EditDraftContract;
use App\Modules\Lease\Filament\App\Resources\Contracts\Pages\ListContracts;
use App\Modules\Lease\Filament\App\Resources\Contracts\Pages\ViewContract;
use App\Modules\Lease\Filament\App\Resources\Contracts\RelationManagers\HoldsRelationManager;
use App\Modules\Lease\Filament\App\Resources\Contracts\RelationManagers\OccupantsRelationManager;
use App\Modules\Lease\Filament\App\Resources\Contracts\Schemas\ContractForm;
use App\Modules\Lease\Filament\App\Resources\Contracts\Schemas\ContractInfolist;
use App\Modules\Lease\Filament\App\Resources\Contracts\Tables\ContractsTable;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Filament\App\RelationManagers\CreditTransactionsRelationManager;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ContractResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Contract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Penghuni';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'kontrak';

    protected static ?string $pluralModelLabel = 'kontrak';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $slug = 'kontrak';

    public static function form(Schema $schema): Schema
    {
        return ContractForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractsTable::configure($table);
    }

    /**
     * @return Builder<Contract>
     */
    public static function getEloquentQuery(): Builder
    {
        return Contract::query()->accessibleBy(User::current());
    }

    public static function getRelations(): array
    {
        return [
            OccupantsRelationManager::class,
            HoldsRelationManager::class,
            DepositTransactionsRelationManager::class,
            CreditTransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContracts::route('/'),
            'create' => CreateContract::route('/tambah'),
            'view' => ViewContract::route('/{record}'),
            'edit' => EditDraftContract::route('/{record}/ubah'),
        ];
    }
}
