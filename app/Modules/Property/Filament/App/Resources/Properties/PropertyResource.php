<?php

namespace App\Modules\Property\Filament\App\Resources\Properties;

use App\Modules\Access\Models\User;
use App\Modules\Property\Filament\App\Resources\Properties\Pages\CreateProperty;
use App\Modules\Property\Filament\App\Resources\Properties\Pages\EditProperty;
use App\Modules\Property\Filament\App\Resources\Properties\Pages\EditPropertySettings;
use App\Modules\Property\Filament\App\Resources\Properties\Pages\ListProperties;
use App\Modules\Property\Filament\App\Resources\Properties\RelationManagers\StaffRelationManager;
use App\Modules\Property\Filament\App\Resources\Properties\Schemas\PropertyForm;
use App\Modules\Property\Filament\App\Resources\Properties\Tables\PropertiesTable;
use App\Modules\Property\Models\Property;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PropertyResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Property::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Properti';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'properti';

    protected static ?string $pluralModelLabel = 'properti';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'properti';

    public static function form(Schema $schema): Schema
    {
        return PropertyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PropertiesTable::configure($table);
    }

    /**
     * Managers and caretakers only see the properties they are assigned to.
     *
     * @return Builder<Property>
     */
    public static function getEloquentQuery(): Builder
    {
        return Property::query()->accessibleBy(User::current());
    }

    public static function getRelations(): array
    {
        return [
            StaffRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProperties::route('/'),
            'create' => CreateProperty::route('/tambah'),
            'edit' => EditProperty::route('/{record}/ubah'),
            'settings' => EditPropertySettings::route('/{record}/pengaturan'),
        ];
    }
}
