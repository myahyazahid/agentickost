<?php

namespace App\Modules\Finance\Filament\App\Resources\OpeningBalances\Pages;

use App\Modules\Finance\Filament\App\Resources\Journals\JournalEntryResource;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\OpeningBalanceResource;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\Schemas\OpeningBalanceForm;
use App\Modules\Finance\Models\OpeningBalance;
use App\Modules\Finance\Models\OpeningBalanceLine;
use App\Support\Money\Rupiah;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A posted opening balance, with the journal it produced.
 *
 * @extends ViewRecord<OpeningBalance>
 */
class ViewOpeningBalance extends ViewRecord
{
    protected static string $resource = OpeningBalanceResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Saldo awal per '.$this->getRecord()->cutoff_date->translatedFormat('j F Y');
    }

    public function infolist(Schema $schema): Schema
    {
        $contracts = OpeningBalanceForm::contractOptions();

        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('posted_at')->label('Diposting')->dateTime('j M Y H:i')->placeholder('Belum'),
                    TextEntry::make('journalEntry.number')
                        ->label('Jurnal pembuka')
                        ->placeholder('Belum ada')
                        ->url(fn (OpeningBalance $record): ?string => $record->journal_entry_id !== null
                            ? JournalEntryResource::getUrl('view', ['record' => $record->journal_entry_id])
                            : null),
                ]),
            Section::make('Rincian')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('lines')
                        ->hiddenLabel()
                        ->table([
                            TableColumn::make('Jenis'),
                            TableColumn::make('Kontrak atau akun'),
                            TableColumn::make('Catatan'),
                            TableColumn::make('Jumlah')->alignment(Alignment::End),
                        ])
                        ->schema([
                            TextEntry::make('kind')->label('Jenis'),
                            TextEntry::make('target')
                                ->label('Kontrak atau akun')
                                ->state(fn (OpeningBalanceLine $record): string => $record->contract_id !== null
                                    ? ($contracts[$record->contract_id] ?? (string) $record->contract?->number)
                                    : (string) $record->account?->name),
                            TextEntry::make('note')->label('Catatan')->placeholder('-'),
                            TextEntry::make('amount')->label('Jumlah')->formatStateUsing(fn (int $state): string => Rupiah::format($state))->alignEnd(),
                        ]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
