<?php

namespace App\Modules\Property\Filament\App\Resources\RoomTypes\Tables;

use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\RoomPrice;
use App\Modules\Property\Models\RoomType;
use App\Modules\Property\Support\RoomPricing;
use App\Support\Filament\MoneyColumn;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RoomTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tipe')
                    ->description(fn (RoomType $record): ?string => $record->property?->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('default_capacity')
                    ->label('Kapasitas')
                    ->suffix(' orang')
                    ->alignEnd(),
                TextColumn::make('rooms_count')
                    ->label('Kamar')
                    ->counts('rooms')
                    ->alignEnd(),
                MoneyColumn::make('monthly_price')
                    ->label('Harga bulanan saat ini')
                    ->state(fn (RoomType $record): ?int => RoomPricing::inForce(
                        RoomPrice::query()->where('room_type_id', $record->id),
                        RentalPeriod::Monthly,
                        now(),
                    )?->amount)
                    ->placeholder('Belum diatur'),
            ])
            ->filters([
                SelectFilter::make('property_id')
                    ->label('Properti')
                    ->options(fn (): array => PropertyOptions::properties()),
            ])
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->with('property'))
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedSquares2x2)
            ->emptyStateHeading('Belum ada tipe kamar')
            ->emptyStateDescription('Tipe kamar mengelompokkan kamar dengan fasilitas dan harga yang sama.');
    }
}
