<?php

namespace App\Modules\Property\Filament\App\Resources\Properties\Tables;

use App\Modules\Access\Models\User;
use App\Modules\Property\Filament\App\Resources\Properties\PropertyResource;
use App\Modules\Property\Models\Property;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PropertiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Properti')
                    ->description(fn (Property $record): string => $record->code)
                    ->searchable(['name', 'code'])
                    ->sortable(),
                TextColumn::make('city')
                    ->label('Kota')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('gender_policy')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('rooms_count')
                    ->label('Kamar')
                    ->counts('rooms')
                    ->alignEnd(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                Action::make('settings')
                    ->label('Pengaturan tagihan')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (Property $record): string => PropertyResource::getUrl('settings', ['record' => $record]))
                    ->visible(fn (Property $record): bool => User::current()->can('manageSettings', $record)),
            ])
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice2)
            ->emptyStateHeading('Belum ada properti')
            ->emptyStateDescription('Tambahkan properti pertama, lalu isi tipe kamar, kamar, dan harganya.');
    }
}
