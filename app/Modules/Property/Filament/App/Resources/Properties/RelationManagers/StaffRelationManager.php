<?php

namespace App\Modules\Property\Filament\App\Resources\Properties\RelationManagers;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Actions\UnassignStaffFromProperty;
use App\Modules\Property\Models\Property;
use App\Support\Filament\DomainActions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Managers and caretakers only see the properties they are assigned to here.
 * Owners and accountants see every property without an assignment.
 */
class StaffRelationManager extends RelationManager
{
    protected static string $relationship = 'staff';

    protected static ?string $title = 'Staf yang ditugaskan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Property && User::current()->can('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Nama'),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->formatStateUsing(fn (string $state): string => Role::tryFrom($state)?->getLabel() ?? $state)
                    ->badge(),
            ])
            ->headerActions([
                Action::make('assign')
                    ->label('Tugaskan staf')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (): bool => User::current()->can('assignStaff', $this->property()))
                    ->schema([
                        Select::make('user_id')
                            ->label('Staf')
                            ->helperText('Hanya manajer dan penjaga yang perlu ditugaskan.')
                            ->options(fn (): array => User::query()
                                ->role([Role::Manager->value, Role::Caretaker->value])
                                ->whereNotIn('id', $this->property()->staff()->select('users.id'))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Action $action, array $data): void {
                        DomainActions::forAction($action, fn () => app(AssignStaffToProperty::class)->handle(
                            $this->property(),
                            User::query()->whereKey($data['user_id'])->firstOrFail(),
                        ));
                    }),
            ])
            ->recordActions([
                Action::make('unassign')
                    ->label('Lepas')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Lepas staf dari properti ini?')
                    ->visible(fn (): bool => User::current()->can('assignStaff', $this->property()))
                    ->action(function (Action $action, User $record): void {
                        DomainActions::forAction($action, fn () => app(UnassignStaffFromProperty::class)->handle($this->property(), $record));
                    }),
            ])
            ->emptyStateHeading('Belum ada staf yang ditugaskan')
            ->emptyStateDescription('Manajer dan penjaga hanya bisa membuka properti yang ditugaskan kepada mereka.');
    }

    private function property(): Property
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Property ? $owner : throw new \LogicException('Relasi staf hanya untuk properti.');
    }
}
