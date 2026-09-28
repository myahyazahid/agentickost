<?php

namespace App\Modules\Payment\Filament\App\Resources\Payments\Tables;

use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\States\Payment\PaymentState;
use App\Modules\Property\Filament\PropertyOptions;
use App\Support\Filament\MoneyColumn;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('paid_at')
                    ->label('Dibayar')
                    ->dateTime('j M Y H:i')
                    ->timezone(fn (Payment $record): string => $record->propertyTimezone())
                    ->description(fn (Payment $record): string => self::methodLabel($record))
                    ->sortable(),
                TextColumn::make('payer.name')
                    ->label('Pembayar')
                    ->description(fn (Payment $record): ?string => $record->contract?->room === null
                        ? null
                        : "Kamar {$record->contract->room->number}, ".($record->property->name ?? '-'))
                    ->searchable(),
                MoneyColumn::make('amount')
                    ->label('Jumlah')
                    ->description(fn (Payment $record): ?string => $record->receipt_number),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['payer', 'property', 'contract.room', 'receivedBy', 'bankAccount']))
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options(self::statusOptions()),
                SelectFilter::make('method')
                    ->label('Cara bayar')
                    ->options(PaymentMethod::manualOptions()),
                SelectFilter::make('property_id')
                    ->label('Properti')
                    ->options(fn (): array => PropertyOptions::properties()),
            ])
            ->recordActions([
                ViewAction::make()->label('Buka'),
            ])
            ->emptyStateIcon(Heroicon::OutlinedBanknotes)
            ->emptyStateHeading('Belum ada pembayaran')
            ->emptyStateDescription('Catat transfer atau uang tunai yang diterima dari penghuni. Pembayaran langsung melunasi tagihan kontraknya.');
    }

    public static function methodLabel(Payment $payment): string
    {
        return match ($payment->method) {
            PaymentMethod::Cash => 'Tunai, diterima '.($payment->receivedBy->name ?? '-'),
            PaymentMethod::Transfer => 'Transfer ke '.($payment->bankAccount->provider_name ?? '-'),
            PaymentMethod::Gateway => $payment->method->getLabel(),
        };
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach (PaymentState::getStateMapping()->keys() as $name) {
            $state = PaymentState::make($name, new Payment);
            $options[$name] = $state instanceof PaymentState ? $state->getLabel() : $name;
        }

        return $options;
    }
}
