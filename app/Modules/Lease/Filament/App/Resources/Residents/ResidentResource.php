<?php

namespace App\Modules\Lease\Filament\App\Resources\Residents;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Filament\App\Resources\Residents\Pages\CreateResident;
use App\Modules\Lease\Filament\App\Resources\Residents\Pages\EditResident;
use App\Modules\Lease\Filament\App\Resources\Residents\Pages\ListResidents;
use App\Modules\Lease\Filament\App\Resources\Residents\Schemas\ResidentForm;
use App\Modules\Lease\Filament\App\Resources\Residents\Tables\ResidentsTable;
use App\Modules\Lease\Models\Resident;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ResidentResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Resident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Penghuni';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'penghuni';

    protected static ?string $pluralModelLabel = 'penghuni';

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $slug = 'penghuni';

    public static function form(Schema $schema): Schema
    {
        return ResidentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResidentsTable::configure($table);
    }

    /**
     * @return Builder<Resident>
     */
    public static function getEloquentQuery(): Builder
    {
        return Resident::query()->accessibleBy(User::current());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResidents::route('/'),
            'create' => CreateResident::route('/tambah'),
            'edit' => EditResident::route('/{record}/ubah'),
        ];
    }
}
