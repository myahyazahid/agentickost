<?php

namespace App\Modules\Lease\Filament\App\Resources\Contracts\Tables;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Property\Filament\PropertyOptions;
use App\Support\Filament\MoneyColumn;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Nomor')
                    ->placeholder('Draf')
                    ->searchable(),
                TextColumn::make('room.number')
                    ->label('Kamar')
                    ->formatStateUsing(fn (string $state): string => "Kamar {$state}")
                    ->description(fn (Contract $record): ?string => $record->property?->name),
                TextColumn::make('primary_resident')
                    ->label('Penghuni utama')
                    ->state(fn (Contract $record): ?string => $record->primaryResident()?->full_name),
                MoneyColumn::make('rent_amount')
                    ->label('Sewa')
                    ->description(fn (Contract $record): string => $record->rental_period->getLabel()),
                TextColumn::make('start_date')
                    ->label('Masa sewa')
                    ->date('j M Y')
                    ->description(fn (Contract $record): string => $record->end_date
                        ? 'sampai '.$record->end_date->translatedFormat('j M Y')
                        : 'sampai diakhiri')
                    ->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->defaultSort('start_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['room', 'property']))
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::statusOptions()),
                SelectFilter::make('property_id')
                    ->label('Properti')
                    ->options(fn (): array => PropertyOptions::properties()),
            ])
            ->recordActions([
                ViewAction::make()->label('Buka'),
            ])
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->emptyStateHeading('Belum ada kontrak')
            ->emptyStateDescription('Buat kontrak untuk penghuni yang akan menempati kamar.');
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach (ContractState::getStateMapping()->keys() as $name) {
            $state = ContractState::make($name, new Contract);
            $options[$name] = $state instanceof ContractState ? $state->getLabel() : $name;
        }

        return $options;
    }
}
