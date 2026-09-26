<?php

namespace App\Modules\Billing\Filament\App\Resources\Invoices\Tables;

use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Draft;
use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Property\Filament\PropertyOptions;
use App\Support\Filament\MoneyColumn;
use App\Support\Money\Rupiah;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Nomor')
                    ->placeholder('Draf')
                    ->description(fn (Invoice $record): string => $record->period_start === null
                        ? $record->type->getLabel()
                        : $record->type->getLabel().', '.$record->period_start->translatedFormat('j M').' sampai '.$record->period_end?->translatedFormat('j M Y'))
                    ->searchable(),
                TextColumn::make('payer.name')
                    ->label('Pembayar')
                    ->description(fn (Invoice $record): ?string => self::roomLabel($record))
                    ->searchable(),
                TextColumn::make('due_date')
                    ->label('Jatuh tempo')
                    ->date('j M Y')
                    ->description(fn (Invoice $record): ?string => ($days = $record->daysLate()) > 0 ? "Telat {$days} hari" : null)
                    ->color(fn (Invoice $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->icon(fn (Invoice $record): ?Heroicon => $record->isOverdue() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->sortable(),
                MoneyColumn::make('balance_amount')
                    ->label('Sisa')
                    ->state(fn (Invoice $record): ?int => $record->status->isOpen() ? max(0, $record->balance_amount) : null)
                    ->description(fn (Invoice $record): string => 'dari '.Rupiah::format(self::total($record)))
                    ->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->defaultSort('due_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['payer', 'property', 'contract.room']))
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->multiple()
                    ->options(self::statusOptions()),
                Filter::make('overdue')
                    ->label('Telat bayar')
                    ->toggle()
                    ->query(fn (Builder $query) => $query
                        ->whereIn('status', InvoiceState::openValues())
                        ->where('balance_amount', '>', 0)
                        ->whereDate('due_date', '<', now())),
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(InvoiceType::class),
                SelectFilter::make('property_id')
                    ->label('Properti')
                    ->options(fn (): array => PropertyOptions::properties()),
            ])
            ->recordActions([
                ViewAction::make()->label('Buka'),
            ])
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->emptyStateHeading('Belum ada tagihan')
            ->emptyStateDescription('Tagihan sewa terbit otomatis sesuai kontrak. Tagihan lain bisa dibuat manual.');
    }

    /**
     * The invoice total with penalties. A draft has no stored total yet.
     */
    public static function total(Invoice $invoice): int
    {
        return $invoice->status->equals(Draft::class)
            ? (int) $invoice->items()->sum('amount')
            : $invoice->items_total_amount + $invoice->penalty_amount;
    }

    public static function roomLabel(Invoice $invoice): ?string
    {
        $room = $invoice->contract?->room?->number;

        return $room === null ? null : "Kamar {$room}, {$invoice->property?->name}";
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach (InvoiceState::getStateMapping()->keys() as $name) {
            $state = InvoiceState::make($name, new Invoice);
            $options[$name] = $state instanceof InvoiceState ? $state->getLabel() : $name;
        }

        return $options;
    }
}
