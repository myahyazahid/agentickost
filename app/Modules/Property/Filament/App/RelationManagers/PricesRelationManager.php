<?php

namespace App\Modules\Property\Filament\App\RelationManagers;

use App\Modules\Access\Models\User;
use App\Modules\Property\Actions\EndRoomPriceOverride;
use App\Modules\Property\Actions\SetRoomPrice;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomType;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyColumn;
use App\Support\Filament\MoneyInput;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Price history of a room type, or the price overrides of a room. Rows are
 * never edited: a new price closes the previous one (FR-KMR-03).
 */
class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return $ownerRecord instanceof Room ? 'Harga khusus kamar ini' : 'Riwayat harga';
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return ($ownerRecord instanceof RoomType || $ownerRecord instanceof Room)
            && User::current()->can('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rental_period')->label('Periode')->badge(),
                MoneyColumn::make('amount')->label('Harga'),
                TextColumn::make('effective_from')->label('Berlaku mulai')->date('j M Y'),
                TextColumn::make('effective_until')
                    ->label('Berlaku sampai')
                    ->date('j M Y')
                    ->placeholder('Masih berlaku'),
            ])
            ->defaultSort('effective_from', 'desc')
            ->headerActions([
                Action::make('setPrice')
                    ->label(fn (): string => $this->target() instanceof Room ? 'Atur harga khusus' : 'Ubah harga')
                    ->icon(Heroicon::OutlinedCurrencyDollar)
                    ->visible(fn (): bool => User::current()->can('managePrices', $this->target()))
                    ->schema([
                        Select::make('rental_period')
                            ->label('Periode sewa')
                            ->options(RentalPeriod::class)
                            ->default(RentalPeriod::Monthly->value)
                            ->required(),
                        MoneyInput::make('amount')->label('Harga per periode')->required(),
                        DatePicker::make('effective_from')
                            ->label('Berlaku mulai')
                            ->helperText('Kontrak yang sudah berjalan tetap memakai harganya sendiri.')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (Action $action, array $data): void {
                        DomainActions::forAction($action, fn () => app(SetRoomPrice::class)->handle($this->target(), $data));
                    }),
                Action::make('endOverride')
                    ->label('Akhiri harga khusus')
                    ->color('gray')
                    ->visible(fn (): bool => $this->target() instanceof Room && User::current()->can('managePrices', $this->target()))
                    ->schema([
                        Select::make('rental_period')
                            ->label('Periode sewa')
                            ->options(RentalPeriod::class)
                            ->required(),
                        DatePicker::make('effective_from')
                            ->label('Kembali ke harga tipe mulai')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (Action $action, array $data): void {
                        $room = $this->target();

                        if (! $room instanceof Room) {
                            return;
                        }

                        $period = $data['rental_period'];

                        DomainActions::forAction($action, fn () => app(EndRoomPriceOverride::class)->handle(
                            $room,
                            $period instanceof RentalPeriod ? $period : RentalPeriod::from($period),
                            $data['effective_from'],
                        ));
                    }),
            ])
            ->emptyStateHeading(fn (): string => $this->target() instanceof Room ? 'Kamar ini memakai harga tipenya' : 'Belum ada harga')
            ->emptyStateDescription(fn (): string => $this->target() instanceof Room
                ? 'Atur harga khusus bila kamar ini lebih mahal atau lebih murah dari tipenya.'
                : 'Isi harga per periode sewa agar kamar dengan tipe ini bisa disewakan.');
    }

    private function target(): RoomType|Room
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof RoomType || $owner instanceof Room
            ? $owner
            : throw new LogicException('Harga hanya untuk tipe kamar atau kamar.');
    }
}
