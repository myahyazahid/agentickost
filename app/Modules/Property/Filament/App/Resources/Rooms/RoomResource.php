<?php

namespace App\Modules\Property\Filament\App\Resources\Rooms;

use App\Modules\Access\Models\User;
use App\Modules\Property\Filament\App\RelationManagers\PricesRelationManager;
use App\Modules\Property\Filament\App\Resources\Rooms\Pages\CreateRoom;
use App\Modules\Property\Filament\App\Resources\Rooms\Pages\EditRoom;
use App\Modules\Property\Filament\App\Resources\Rooms\Pages\ListRooms;
use App\Modules\Property\Filament\App\Resources\Rooms\Schemas\RoomForm;
use App\Modules\Property\Filament\App\Resources\Rooms\Tables\RoomsTable;
use App\Modules\Property\Models\Room;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RoomResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Room::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Properti';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'kamar';

    protected static ?string $pluralModelLabel = 'kamar';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $slug = 'kamar';

    public static function form(Schema $schema): Schema
    {
        return RoomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomsTable::configure($table);
    }

    /**
     * @return Builder<Room>
     */
    public static function getEloquentQuery(): Builder
    {
        return Room::query()->accessibleBy(User::current());
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
            'index' => ListRooms::route('/'),
            'create' => CreateRoom::route('/tambah'),
            'edit' => EditRoom::route('/{record}/ubah'),
        ];
    }
}
