<?php

namespace App\Modules\Payment\Filament\App\Resources\Payments\Schemas;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Filament\App\Resources\Invoices\InvoiceResource;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Lease\Filament\App\Resources\Contracts\ContractResource;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Filament\App\Resources\Payments\Tables\PaymentsTable;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\States\Payment\Rejected;
use App\Modules\Payment\States\Payment\Reversed;
use App\Support\Actors\ActorType;
use App\Support\Money\Rupiah;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (mixed $state): ?string => is_numeric($state) ? Rupiah::format((int) $state) : null;
        $timezone = fn (Payment $record): string => $record->propertyTimezone();

        return $schema->components([
            Section::make('Ditolak')
                ->icon(Heroicon::OutlinedXCircle)
                ->iconColor('danger')
                ->columnSpanFull()
                ->visible(fn (Payment $record): bool => $record->status->equals(Rejected::class))
                ->schema([
                    TextEntry::make('rejection_reason')->label('Alasan'),
                ]),
            Section::make('Dibalik')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->iconColor('danger')
                ->columnSpanFull()
                ->columns(2)
                ->visible(fn (Payment $record): bool => $record->status->equals(Reversed::class))
                ->schema([
                    TextEntry::make('reversed_at')->label('Dibalik pada')->dateTime('j F Y H:i')->timezone($timezone),
                    TextEntry::make('reversal_reason')->label('Alasan'),
                ]),
            Section::make('Ringkasan')
                ->columns(['default' => 1, 'md' => 3])
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('amount')->label('Jumlah')->formatStateUsing($money)->weight('bold'),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('receipt_number')->label('Nomor kuitansi')->placeholder('Diberikan saat terverifikasi'),
                    TextEntry::make('payer.name')->label('Pembayar'),
                    TextEntry::make('contract.number')
                        ->label('Kontrak')
                        ->formatStateUsing(fn (string $state, Payment $record): string => "{$state}, kamar {$record->contract?->room?->number}")
                        ->url(fn (Payment $record): ?string => $record->contract_id === null ? null : ContractResource::getUrl('view', ['record' => $record->contract_id]))
                        ->placeholder('-'),
                    TextEntry::make('paid_at')->label('Waktu bayar')->dateTime('j F Y H:i')->timezone($timezone),
                    TextEntry::make('method')
                        ->label('Cara bayar')
                        ->state(fn (Payment $record): string => PaymentsTable::methodLabel($record)),
                    TextEntry::make('reference')
                        ->label('Referensi')
                        ->placeholder('-')
                        ->visible(fn (Payment $record): bool => $record->method === PaymentMethod::Transfer),
                    TextEntry::make('verified_at')
                        ->label('Diperiksa')
                        ->state(fn (Payment $record): ?string => self::reviewedBy($record))
                        ->placeholder('Belum diperiksa'),
                ]),
            Section::make('Bukti bayar')
                ->columnSpanFull()
                ->visible(fn (Payment $record): bool => $record->attachments()->exists())
                ->schema([
                    TextEntry::make('proofs')
                        ->hiddenLabel()
                        ->state(fn (Payment $record): HtmlString => self::proofLinks($record))
                        ->html(),
                ]),
            Section::make('Dipakai untuk')
                ->columnSpanFull()
                ->visible(fn (Payment $record): bool => $record->allocations()->exists() || self::credited($record) > 0)
                ->schema([
                    RepeatableEntry::make('allocations')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Tagihan'),
                            TableColumn::make('Bagian'),
                            TableColumn::make('Jumlah')->alignment(Alignment::End),
                        ])
                        ->schema([
                            TextEntry::make('invoice.number')
                                ->label('Tagihan')
                                ->url(fn (PaymentAllocation $record): string => InvoiceResource::getUrl('view', ['record' => $record->invoice_id])),
                            TextEntry::make('allocation_category')
                                ->label('Bagian')
                                ->formatStateUsing(fn (PaymentAllocation $record): string => $record->allocation_category->getLabel().($record->isActive() ? '' : ' (dibatalkan)'))
                                ->color(fn (PaymentAllocation $record): ?string => $record->isActive() ? null : 'gray'),
                            TextEntry::make('amount')->label('Jumlah')->formatStateUsing($money)->alignEnd(),
                        ]),
                    TextEntry::make('credited')
                        ->label('Disimpan sebagai saldo kredit')
                        ->state(fn (Payment $record): int => self::credited($record))
                        ->formatStateUsing($money)
                        ->visible(fn (Payment $record): bool => self::credited($record) > 0),
                ]),
        ]);
    }

    /**
     * Credit this payment still leaves on the contract.
     */
    public static function credited(Payment $payment): int
    {
        return (int) $payment->creditTransactions()->sum('amount');
    }

    private static function reviewedBy(Payment $payment): ?string
    {
        if ($payment->verified_at === null) {
            return null;
        }

        $who = match ($payment->verified_by_type) {
            ActorType::User => User::query()->find($payment->verified_by_id)->name ?? 'staf',
            default => 'sistem',
        };

        return $payment->verified_at->timezone($payment->propertyTimezone())->translatedFormat('j F Y H:i').", oleh {$who}";
    }

    private static function proofLinks(Payment $payment): HtmlString
    {
        $links = $payment->attachments()
            ->where('collection', AttachmentCollection::PaymentProof->value)
            ->oldest('id')
            ->get()
            ->map(fn (Attachment $file): string => '<a class="underline" target="_blank" rel="noopener" href="'.e($file->temporaryUrl(30)).'">'.e($file->original_name).'</a>');

        return new HtmlString($links->implode('<br>'));
    }
}
