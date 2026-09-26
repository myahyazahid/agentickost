<?php

namespace App\Modules\Property\Filament\App\Resources\Rooms\Tables;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Support\Arrears;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Property\Actions\FinishRoomMaintenance;
use App\Modules\Property\Actions\StartRoomMaintenance;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Room;
use App\Modules\Property\States\Room\Available;
use App\Modules\Property\States\Room\Maintenance;
use App\Modules\Property\States\Room\RoomState;
use App\Modules\Property\Support\RoomPricing;
use App\Support\Filament\DomainActions;
use App\Support\Money\Rupiah;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Room grid per property (FR-KMR-05): one card per room with its status.
 */
class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    TextColumn::make('number')
                        ->label('Nomor kamar')
                        ->formatStateUsing(fn (string $state): string => "Kamar {$state}")
                        ->weight(FontWeight::Bold)
                        ->size(TextSize::Large)
                        ->searchable(),
                    TextColumn::make('status')
                        ->label('Status')
                        ->badge(),
                    TextColumn::make('occupant')
                        ->label('Penghuni')
                        ->state(fn (Room $record): ?string => Contract::query()
                            ->where('room_id', $record->id)
                            ->whereIn('status', ContractState::runningValues())
                            ->first()
                            ?->primaryResident()
                            ?->full_name)
                        ->icon(Heroicon::OutlinedUser)
                        ->placeholder('Belum ada penghuni'),
                    TextColumn::make('arrears')
                        ->label('Tunggakan')
                        ->state(fn (Room $record): ?string => ($amount = Arrears::forRoom($record)) > 0
                            ? 'Tunggakan '.Rupiah::format($amount)
                            : null)
                        ->icon(Heroicon::OutlinedExclamationTriangle)
                        ->color('danger'),
                    TextColumn::make('roomType.name')
                        ->label('Tipe'),
                    TextColumn::make('monthly_price')
                        ->label('Harga bulanan')
                        ->state(fn (Room $record): ?string => ($amount = app(RoomPricing::class)->priceFor($record, RentalPeriod::Monthly, now())) !== null
                            ? Rupiah::format($amount).' per bulan'
                            : null)
                        ->placeholder('Harga bulanan belum diatur')
                        ->color('gray'),
                    TextColumn::make('property.name')
                        ->label('Properti')
                        ->size(TextSize::Small)
                        ->color('gray'),
                ])->space(2),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 4,
            ])
            ->defaultSort('number')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['roomType', 'property']))
            ->filters([
                SelectFilter::make('property_id')
                    ->label('Properti')
                    ->options(fn (): array => PropertyOptions::properties()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::statusOptions()),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('startMaintenance')
                    ->label('Mulai perbaikan')
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Kamar tidak bisa disewakan sampai perbaikan selesai.')
                    ->visible(fn (Room $record): bool => $record->status->equals(Available::class)
                        && User::current()->can('manageStatus', $record))
                    ->action(function (Action $action, Room $record): void {
                        DomainActions::forAction($action, fn () => app(StartRoomMaintenance::class)->handle($record));
                    }),
                Action::make('finishMaintenance')
                    ->label('Selesai perbaikan')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Room $record): bool => $record->status->equals(Maintenance::class)
                        && User::current()->can('manageStatus', $record))
                    ->action(function (Action $action, Room $record): void {
                        DomainActions::forAction($action, fn () => app(FinishRoomMaintenance::class)->handle($record));
                    }),
            ])
            ->emptyStateIcon(Heroicon::OutlinedKey)
            ->emptyStateHeading('Belum ada kamar')
            ->emptyStateDescription('Buat tipe kamar dulu, lalu tambahkan kamar untuk setiap properti.');
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach (RoomState::getStateMapping()->keys() as $name) {
            $state = RoomState::make($name, new Room);
            $options[$name] = $state instanceof RoomState ? $state->getLabel() : $name;
        }

        return $options;
    }
}
