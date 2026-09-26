<?php

namespace App\Modules\Lease\Filament\App\Resources\Contracts\Schemas;

use App\Modules\Lease\Filament\App\Resources\Contracts\ContractResource;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\Notice;
use App\Modules\Lease\States\Contract\Terminated;
use App\Support\Money\Rupiah;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContractInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (mixed $state): ?string => is_numeric($state) ? Rupiah::format((int) $state) : null;

        return $schema->components([
            Section::make('Ringkasan')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('number')->label('Nomor')->placeholder('Diberikan saat kontrak diaktifkan'),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('room.number')
                        ->label('Kamar')
                        ->formatStateUsing(fn (string $state, Contract $record): string => "Kamar {$state}, {$record->property?->name}"),
                    TextEntry::make('primary_resident')
                        ->label('Penghuni utama')
                        ->state(fn (Contract $record): ?string => $record->primaryResident()?->full_name),
                    TextEntry::make('payer.name')
                        ->label('Pembayar')
                        ->formatStateUsing(fn (string $state, Contract $record): string => "{$state} ({$record->payer?->relation->getLabel()})")
                        ->helperText(fn (Contract $record): ?string => $record->payer?->phone),
                    TextEntry::make('notifications')
                        ->label('Tagihan dikirim ke')
                        ->state(fn (Contract $record): string => implode(' dan ', array_filter([
                            $record->notify_resident ? 'penghuni' : null,
                            $record->notify_payer && $record->payer?->resident_id === null ? 'pembayar' : null,
                        ])) ?: 'tidak ada'),
                ]),
            Section::make('Sewa')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('rental_period')->label('Periode bayar'),
                    TextEntry::make('rent_amount')->label('Sewa per periode')->formatStateUsing($money),
                    TextEntry::make('deposit_amount')->label('Deposit')->formatStateUsing($money),
                    TextEntry::make('start_date')->label('Mulai')->date('j F Y'),
                    TextEntry::make('end_date')->label('Selesai')->date('j F Y')->placeholder('Sampai diakhiri'),
                    TextEntry::make('billing_anchor_day')->label('Jatuh tempo')->formatStateUsing(fn (int $state): string => "Setiap tanggal {$state}"),
                    TextEntry::make('early_termination_penalty_amount')
                        ->label('Denda pemutusan dini')
                        ->formatStateUsing($money)
                        ->placeholder('Tidak ada'),
                ]),
            Section::make('Rencana keluar')
                ->columns(3)
                ->columnSpanFull()
                ->visible(fn (Contract $record): bool => $record->status->equals(Notice::class, Terminated::class))
                ->schema([
                    TextEntry::make('notice_given_on')->label('Diberitahukan')->date('j F Y')->placeholder('-'),
                    TextEntry::make('planned_move_out_on')->label('Rencana keluar')->date('j F Y')->placeholder('-'),
                    TextEntry::make('ended_on')->label('Kontrak berakhir')->date('j F Y')->placeholder('-'),
                    TextEntry::make('termination_reason')->label('Alasan pemutusan')->placeholder('-')->columnSpan(2),
                    TextEntry::make('termination_penalty_amount')->label('Denda pemutusan')->formatStateUsing($money)->placeholder('Tidak ada'),
                ]),
            Section::make('Perpanjangan')
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (Contract $record): bool => $record->renewed_from_contract_id !== null || $record->renewal()->exists())
                ->schema([
                    TextEntry::make('renewedFrom.number')
                        ->label('Memperpanjang kontrak')
                        ->url(fn (Contract $record): ?string => $record->renewed_from_contract_id
                            ? ContractResource::getUrl('view', ['record' => $record->renewed_from_contract_id])
                            : null)
                        ->placeholder('-'),
                    TextEntry::make('renewal_link')
                        ->label('Diperpanjang dengan')
                        ->state(function (Contract $record): ?string {
                            $renewal = $record->renewal()->first();

                            return $renewal === null ? null : ($renewal->number ?? 'Draf mulai '.$renewal->start_date->translatedFormat('j F Y'));
                        })
                        ->url(fn (Contract $record): ?string => ($renewal = $record->renewal()->first())
                            ? ContractResource::getUrl('view', ['record' => $renewal])
                            : null)
                        ->placeholder('-'),
                ]),
        ]);
    }
}
