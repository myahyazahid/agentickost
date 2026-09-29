<?php

namespace App\Modules\Access\Filament\App\Resources\Users;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Filament\App\Resources\Users\Pages\ListUsers;
use App\Modules\Access\Models\User;
use App\Modules\Property\Models\Property;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The tenant's owner and staff, with their role and the properties they
 * see (FR-USR-01 to FR-USR-03). Staff join by invitation.
 */
class UserResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Properti';

    protected static ?int $navigationSort = 80;

    protected static ?string $modelLabel = 'staf';

    protected static ?string $pluralModelLabel = 'staf';

    protected static ?string $slug = 'staf';

    /**
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        return User::query()->with('roles');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->state(fn (User $record): ?string => self::role($record)?->getLabel()),
                TextColumn::make('properties')
                    ->label('Properti')
                    ->state(fn (User $record): string => self::propertiesOf($record))
                    ->wrap(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('Belum ada staf')
            ->emptyStateDescription('Undang manajer, penjaga, atau akuntan lewat email. Mereka membuat password sendiri dari tautan undangan.');
    }

    public static function role(User $user): ?Role
    {
        return Role::tryFrom((string) $user->roles->first()?->getAttribute('name'));
    }

    /**
     * Owners and accountants see every property (FR-USR-02).
     */
    public static function propertiesOf(User $user): string
    {
        if (self::role($user)?->seesAllProperties()) {
            return 'Semua properti';
        }

        $names = Property::query()
            ->whereHas('staff', fn (Builder $query) => $query->whereKey($user->id))
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $names === [] ? 'Belum ada properti' : implode(', ', $names);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
