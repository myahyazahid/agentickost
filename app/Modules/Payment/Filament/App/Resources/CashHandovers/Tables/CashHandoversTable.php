<?php

namespace App\Modules\Payment\Filament\App\Resources\CashHandovers\Tables;

use App\Modules\Access\Models\User;
use App\Modules\Payment\Actions\ConfirmCashHandover;
use App\Modules\Payment\Actions\DisputeCashHandover;
use App\Modules\Payment\Models\StaffCashHandover;
use App\Modules\Payment\States\Handover\Confirmed;
use App\Modules\Payment\States\Handover\Disputed;
use App\Modules\Payment\States\Handover\Pending;
use App\Modules\Property\Filament\PropertyOptions;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyColumn;
use App\Support\Filament\MoneyInput;
use App\Support\Money\Rupiah;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CashHandoversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('handed_over_at')
                    ->label('Disetor')
                    ->dateTime('j M Y H:i')
                    ->timezone(fn (StaffCashHandover $record): string => $record->propertyTimezone())
                    ->description(fn (StaffCashHandover $record): string => 'ke '.($record->destinationAccount->name ?? '-'))
                    ->sortable(),
                TextColumn::make('staff.name')
                    ->label('Staf')
                    ->description(fn (StaffCashHandover $record): string => $record->property->name ?? '-'),
                MoneyColumn::make('actual_amount')
                    ->label('Disetor')
                    ->description(fn (StaffCashHandover $record): string => 'saldo kas '.Rupiah::format($record->expected_amount)),
                TextColumn::make('difference_amount')
                    ->label('Selisih')
                    ->state(fn (StaffCashHandover $record): ?string => self::difference($record))
                    ->color(fn (StaffCashHandover $record): ?string => $record->hasDifference() ? 'danger' : null)
                    ->icon(fn (StaffCashHandover $record): ?Heroicon => $record->hasDifference() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->description(fn (StaffCashHandover $record): ?string => $record->difference_note ?? $record->dispute_note)
                    ->placeholder('Cocok')
                    ->wrap(),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->defaultSort('handed_over_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['staff', 'property', 'destinationAccount']))
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Pending::$name => 'Menunggu konfirmasi',
                        Disputed::$name => 'Dipersoalkan',
                        Confirmed::$name => 'Diterima',
                    ]),
                SelectFilter::make('property_id')
                    ->label('Properti')
                    ->options(fn (): array => PropertyOptions::properties()),
            ])
            ->recordActions([
                self::confirmAction(),
                self::disputeAction(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedInboxArrowDown)
            ->emptyStateHeading('Belum ada setoran')
            ->emptyStateDescription('Staf yang menerima uang tunai mencatat setorannya di sini, lalu owner menerima atau mempersoalkannya.');
    }

    private static function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label('Terima')
            ->icon(Heroicon::OutlinedCheck)
            ->visible(fn (StaffCashHandover $record): bool => ! $record->status->equals(Confirmed::class) && User::current()->can('confirm', $record))
            ->modalHeading('Terima setoran')
            ->modalDescription(fn (StaffCashHandover $record): string => 'Saldo kas '.($record->staff->name ?? 'staf').' saat setor '.Rupiah::format($record->expected_amount).'. Isi jumlah yang benar-benar Anda terima.')
            ->fillForm(fn (StaffCashHandover $record): array => ['actual_amount' => $record->actual_amount])
            ->schema([
                MoneyInput::make('actual_amount')->label('Uang yang diterima')->required(),
                Textarea::make('difference_note')
                    ->label('Penjelasan selisih')
                    ->helperText('Wajib bila jumlahnya berbeda dari saldo kas.'),
            ])
            ->modalSubmitActionLabel('Terima setoran')
            ->action(function (Action $action, StaffCashHandover $record, array $data): void {
                DomainActions::forAction($action, fn () => app(ConfirmCashHandover::class)->handle($record, $data));

                Notification::make()->success()->title('Setoran diterima')->send();
            });
    }

    private static function disputeAction(): Action
    {
        return Action::make('dispute')
            ->label('Persoalkan')
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('danger')
            ->visible(fn (StaffCashHandover $record): bool => $record->status->equals(Pending::class) && User::current()->can('confirm', $record))
            ->modalHeading('Persoalkan setoran')
            ->modalDescription('Setoran tetap terbuka sampai Anda menerimanya dengan penjelasan.')
            ->schema([
                Textarea::make('dispute_note')->label('Masalahnya')->placeholder('Misal: uang di amplop hanya Rp2.300.000')->required()->minLength(5),
            ])
            ->modalSubmitActionLabel('Persoalkan')
            ->action(function (Action $action, StaffCashHandover $record, array $data): void {
                DomainActions::forAction($action, fn () => app(DisputeCashHandover::class)->handle($record, $data));
            });
    }

    private static function difference(StaffCashHandover $handover): ?string
    {
        $difference = $handover->actual_amount - $handover->expected_amount;

        if ($difference === 0) {
            return null;
        }

        return ($difference < 0 ? 'Kurang ' : 'Lebih ').Rupiah::format(abs($difference));
    }
}
