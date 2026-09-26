<?php

namespace App\Modules\Billing\Filament\App\Resources\Invoices\Schemas;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Voided;
use App\Modules\Lease\Filament\App\Resources\Contracts\ContractResource;
use App\Support\Money\Rupiah;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (mixed $state): ?string => is_numeric($state) ? Rupiah::format((int) $state) : null;

        return $schema->components([
            Section::make('Dibatalkan')
                ->icon(Heroicon::OutlinedXCircle)
                ->iconColor('danger')
                ->columnSpanFull()
                ->columns(2)
                ->visible(fn (Invoice $record): bool => $record->status->equals(Voided::class))
                ->schema([
                    TextEntry::make('voided_at')->label('Dibatalkan pada')->dateTime('j F Y H:i'),
                    TextEntry::make('void_reason')->label('Alasan'),
                ]),
            Section::make('Ringkasan')
                ->columns(['default' => 1, 'md' => 3])
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('number')->label('Nomor')->placeholder('Diberikan saat tagihan terbit'),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('type')->label('Jenis'),
                    TextEntry::make('payer.name')
                        ->label('Pembayar')
                        ->helperText(fn (Invoice $record): ?string => $record->payer?->phone),
                    TextEntry::make('contract.number')
                        ->label('Kontrak')
                        ->formatStateUsing(fn (string $state, Invoice $record): string => "{$state}, kamar {$record->contract?->room?->number}")
                        ->url(fn (Invoice $record): ?string => $record->contract_id === null ? null : ContractResource::getUrl('view', ['record' => $record->contract_id]))
                        ->placeholder('-'),
                    TextEntry::make('period_start')
                        ->label('Periode')
                        ->state(fn (Invoice $record): ?string => $record->period_start === null ? null
                            : $record->period_start->translatedFormat('j F Y').' sampai '.$record->period_end?->translatedFormat('j F Y'))
                        ->placeholder('-'),
                    TextEntry::make('issue_date')->label('Terbit')->date('j F Y')->placeholder('Belum terbit'),
                    TextEntry::make('due_date')
                        ->label('Jatuh tempo')
                        ->date('j F Y')
                        ->color(fn (Invoice $record): ?string => $record->isOverdue() ? 'danger' : null)
                        ->icon(fn (Invoice $record): ?Heroicon => $record->isOverdue() ? Heroicon::OutlinedExclamationTriangle : null)
                        ->helperText(fn (Invoice $record): ?string => ($days = $record->daysLate()) > 0 ? "Telat {$days} hari" : null),
                ]),
            Section::make('Rincian')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Keterangan'),
                            TableColumn::make('Jumlah')->alignment(Alignment::End),
                        ])
                        ->schema([
                            TextEntry::make('description')->label('Keterangan'),
                            TextEntry::make('amount')->label('Jumlah')->formatStateUsing($money)->alignEnd(),
                        ]),
                ]),
            Section::make('Denda keterlambatan')
                ->columnSpanFull()
                ->visible(fn (Invoice $record): bool => $record->penalties()->exists())
                ->schema([
                    RepeatableEntry::make('penalties')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Tanggal'),
                            TableColumn::make('Keterangan'),
                            TableColumn::make('Jumlah')->alignment(Alignment::End),
                        ])
                        ->schema([
                            TextEntry::make('accrued_on')->label('Tanggal')->date('j M Y'),
                            TextEntry::make('waive_reason')
                                ->label('Keterangan')
                                ->state(fn ($record): string => $record->isWaived() ? "Dihapus: {$record->waive_reason}" : 'Berlaku')
                                ->color(fn ($record): ?string => $record->isWaived() ? 'gray' : null),
                            TextEntry::make('amount')->label('Jumlah')->formatStateUsing($money)->alignEnd(),
                        ]),
                ]),
            Section::make('Nota kredit')
                ->columnSpanFull()
                ->visible(fn (Invoice $record): bool => $record->creditNotes()->exists())
                ->schema([
                    RepeatableEntry::make('creditNotes')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Nomor'),
                            TableColumn::make('Alasan'),
                            TableColumn::make('Jumlah')->alignment(Alignment::End),
                        ])
                        ->schema([
                            TextEntry::make('number')->label('Nomor'),
                            TextEntry::make('reason')->label('Alasan'),
                            TextEntry::make('amount')->label('Jumlah')->formatStateUsing($money)->alignEnd(),
                        ]),
                ]),
            Section::make('Jumlah')
                ->columns(['default' => 2, 'md' => 5])
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('items_total_amount')->label('Rincian')->formatStateUsing($money),
                    TextEntry::make('penalty_amount')->label('Denda')->formatStateUsing($money),
                    TextEntry::make('credited_amount')->label('Nota kredit')->formatStateUsing($money),
                    TextEntry::make('paid_amount')->label('Sudah dibayar')->formatStateUsing($money),
                    TextEntry::make('balance_amount')
                        ->label('Sisa')
                        ->state(fn (Invoice $record): int => $record->status->isOpen() ? max(0, $record->balance_amount) : 0)
                        ->formatStateUsing($money)
                        ->weight('bold'),
                ]),
        ]);
    }
}
