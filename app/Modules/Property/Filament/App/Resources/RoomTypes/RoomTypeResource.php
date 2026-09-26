<?php

namespace App\Modules\Property\Filament\App\Resources\RoomTypes;

use App\Modules\Access\Models\User;
use App\Modules\Property\Filament\App\RelationManagers\PricesRelationManager;
use App\Modules\Property\Filament\App\Resources\RoomTypes\Pages\CreateRoomType;
use App\Modules\Property\Filament\App\Resources\RoomTypes\Pages\EditRoomType;
use App\Modules\Property\Filament\App\Resources\RoomTypes\Pages\ListRoomTypes;
use App\Modules\Property\Filament\App\Resources\RoomTypes\Schemas\RoomTypeForm;
use App\Modules\Property\Filament\App\Resources\RoomTypes\Tables\RoomTypesTable;
use App\Modules\Property\Models\RoomType;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RoomTypeResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = RoomType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Properti';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'tipe kamar';

    protected static ?string $pluralModelLabel = 'tipe kamar';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'tipe-kamar';

    public static function form(Schema $schema): Schema
    {
        return RoomTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomTypesTable::configure($table);
    }

    /**
     * @return Builder<RoomType>
     */
    public static function getEloquentQuery(): Builder
    {
        return RoomType::query()->accessibleBy(User::current());
    }

    public static function getRelations(): array
    {
        return [
            PricesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoomTypes::route('/'),
            'create' => CreateRoomType::route('/tambah'),
            'edit' => EditRoomType::route('/{record}/ubah'),
        ];
    }
}
