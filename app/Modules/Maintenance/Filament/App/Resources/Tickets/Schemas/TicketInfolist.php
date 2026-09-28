<?php

namespace App\Modules\Maintenance\Filament\App\Resources\Tickets\Schemas;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Filament\App\Resources\Invoices\InvoiceResource;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\Models\TicketUpdate;
use App\Modules\Maintenance\States\Ticket\TicketState;
use App\Support\Actors\ActorType;
use App\Support\Money\Rupiah;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(['default' => 1, 'md' => 3])
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('priority')->label('Prioritas')->badge(),
                    TextEntry::make('category')->label('Kategori'),
                    TextEntry::make('location')
                        ->label('Lokasi')
                        ->state(fn (Ticket $record): string => "{$record->location()}, {$record->property?->name}"),
                    TextEntry::make('assignee.name')->label('Dikerjakan oleh')->placeholder('Belum ditugaskan'),
                    TextEntry::make('created_at')->label('Dilaporkan')->since(),
                    TextEntry::make('description')->label('Keterangan')->columnSpanFull(),
                ]),
            Section::make('Foto')
                ->columns(['default' => 1, 'md' => 2])
                ->columnSpanFull()
                ->visible(fn (Ticket $record): bool => $record->attachments()->exists())
                ->schema([
                    TextEntry::make('before')->label('Sebelum')->state(fn (Ticket $record): HtmlString => self::photoLinks($record, AttachmentCollection::Before))->html()->placeholder('-'),
                    TextEntry::make('after')->label('Sesudah')->state(fn (Ticket $record): HtmlString => self::photoLinks($record, AttachmentCollection::After))->html()->placeholder('-'),
                ]),
            Section::make('Biaya')
                ->columns(['default' => 1, 'md' => 3])
                ->columnSpanFull()
                ->visible(fn (Ticket $record): bool => $record->cost_amount > 0)
                ->schema([
                    TextEntry::make('cost_amount')->label('Biaya perbaikan')->formatStateUsing(fn (mixed $state): string => Rupiah::format((int) $state)),
                    TextEntry::make('paidFromAccount.name')->label('Dibayar dari')->placeholder('-'),
                    TextEntry::make('charge')
                        ->label('Ditagihkan ke penghuni')
                        ->state(fn (Ticket $record): string => match (true) {
                            ! $record->charge_to_resident => 'Tidak',
                            $record->charge_invoice_id !== null => 'Ya, tagihan '.($record->chargeInvoice->number ?? '-'),
                            default => 'Ya, ditagih saat tiket dikonfirmasi',
                        })
                        ->url(fn (Ticket $record): ?string => $record->charge_invoice_id === null ? null : InvoiceResource::getUrl('view', ['record' => $record->charge_invoice_id])),
                ]),
            Section::make('Riwayat')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('updates')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Waktu'),
                            TableColumn::make('Oleh'),
                            TableColumn::make('Perubahan'),
                        ])
                        ->schema([
                            TextEntry::make('created_at')->label('Waktu')->dateTime('j M Y H:i'),
                            TextEntry::make('actor_id')->label('Oleh')->state(fn (TicketUpdate $record): string => self::actorName($record)),
                            TextEntry::make('note')
                                ->label('Perubahan')
                                ->state(fn (TicketUpdate $record): string => self::change($record)),
                        ]),
                ]),
        ]);
    }

    private static function change(TicketUpdate $update): string
    {
        $status = $update->to_status === null ? null : TicketState::make($update->to_status, new Ticket);
        $label = $status instanceof TicketState ? $status->getLabel() : null;

        return implode(': ', array_filter([$label, $update->note]));
    }

    private static function actorName(TicketUpdate $update): string
    {
        return $update->actor_type === ActorType::User
            ? (User::query()->find($update->actor_id)->name ?? 'Staf')
            : 'Sistem';
    }

    private static function photoLinks(Ticket $ticket, AttachmentCollection $collection): HtmlString
    {
        $links = $ticket->attachments()
            ->where('collection', $collection->value)
            ->oldest('id')
            ->get()
            ->map(fn (Attachment $file): string => '<a class="underline" target="_blank" rel="noopener" href="'.e($file->temporaryUrl(30)).'">'.e($file->original_name).'</a>');

        return new HtmlString($links->implode('<br>'));
    }
}
