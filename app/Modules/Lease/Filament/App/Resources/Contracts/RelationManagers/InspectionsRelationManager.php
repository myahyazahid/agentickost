<?php

namespace App\Modules\Lease\Filament\App\Resources\Contracts\RelationManagers;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Lease\Actions\AcknowledgeInspection;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Inspection;
use App\Modules\Lease\Models\InspectionItem;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyColumn;
use App\Support\Money\Rupiah;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Check-in and check-out inspections of the contract (FR-SIK-01, FR-SIK-04).
 */
class InspectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'inspections';

    protected static ?string $title = 'Pemeriksaan kamar';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Contract && User::current()->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->label('Jenis')->badge()->color('gray'),
                TextColumn::make('inspected_on')
                    ->label('Tanggal')
                    ->date('j M Y')
                    ->description(fn (Inspection $record): string => 'Kamar '.($record->room->number ?? '-').', oleh '.($record->inspector->name ?? '-')),
                TextColumn::make('resident_acknowledged_at')
                    ->label('Disetujui penghuni')
                    ->dateTime('j M Y')
                    ->placeholder('Belum')
                    ->icon(fn (Inspection $record): Heroicon => $record->resident_acknowledged_at === null ? Heroicon::OutlinedClock : Heroicon::OutlinedCheckCircle),
                MoneyColumn::make('charged')
                    ->label('Biaya kerusakan')
                    ->state(fn (Inspection $record): int => $record->chargedAmount())
                    ->placeholder('-'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['room', 'inspector']))
            ->defaultSort('inspected_on')
            ->recordActions([
                Action::make('show')
                    ->label('Lihat')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (Inspection $record): string => "{$record->type->getLabel()} {$record->inspected_on->translatedFormat('j F Y')}")
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Barang'),
                                TableColumn::make('Kondisi'),
                                TableColumn::make('Biaya')->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('item_name')
                                    ->label('Barang')
                                    ->helperText(fn (InspectionItem $record): ?string => $record->notes),
                                TextEntry::make('condition')->label('Kondisi'),
                                TextEntry::make('charge_amount')
                                    ->label('Biaya')
                                    ->formatStateUsing(fn (mixed $state): ?string => (int) $state > 0 ? Rupiah::format((int) $state) : null)
                                    ->placeholder('-')
                                    ->alignEnd(),
                            ]),
                        TextEntry::make('notes')->label('Catatan')->placeholder('-'),
                        TextEntry::make('photos')
                            ->label('Foto')
                            ->state(fn (Inspection $record): HtmlString => self::photoLinks($record))
                            ->html()
                            ->placeholder('Tidak ada foto'),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                Action::make('acknowledge')
                    ->label('Tandai disetujui')
                    ->icon(Heroicon::OutlinedHandThumbUp)
                    ->visible(fn (Inspection $record): bool => $record->resident_acknowledged_at === null
                        && User::current()->can('inspect', $record->contract()->firstOrFail()))
                    ->requiresConfirmation()
                    ->modalDescription('Penghuni sudah membaca hasil pemeriksaan ini dan setuju.')
                    ->action(function (Action $action, Inspection $record): void {
                        DomainActions::forAction($action, fn () => app(AcknowledgeInspection::class)->handle($record));
                    }),
            ])
            ->emptyStateHeading('Belum ada pemeriksaan')
            ->emptyStateDescription('Kondisi kamar dicatat saat check-in dan check-out.');
    }

    private static function photoLinks(Inspection $inspection): HtmlString
    {
        $links = $inspection->attachments()
            ->where('collection', AttachmentCollection::Inspection->value)
            ->oldest('id')
            ->get()
            ->map(fn (Attachment $file): string => '<a class="underline" target="_blank" rel="noopener" href="'.e($file->temporaryUrl(30)).'">'.e($file->original_name).'</a>');

        return new HtmlString($links->implode('<br>'));
    }
}
