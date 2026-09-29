<?php

namespace App\Modules\Finance\Filament\App\Resources\Journals\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Actions\ReverseManualJournal;
use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Filament\App\Resources\Journals\JournalEntryResource;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Tenancy\TenantContext;
use App\Support\Filament\DomainActions;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @extends ViewRecord<JournalEntry>
 */
class ViewJournalEntry extends ViewRecord
{
    protected static string $resource = JournalEntryResource::class;

    public function getTitle(): string|Htmlable
    {
        return "Jurnal {$this->getRecord()->number}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reverse')
                ->label('Balik jurnal')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('danger')
                ->visible(fn (): bool => $this->isReversible())
                ->modalHeading('Balik jurnal manual')
                ->modalDescription('Jurnal pembalik dengan debit dan kredit tertukar dibuat pada tanggal yang dipilih. Jurnal asal tetap tersimpan.')
                ->schema([
                    DatePicker::make('entry_date')
                        ->label('Tanggal pembalikan')
                        ->default(fn (): string => CarbonImmutable::now(app(TenantContext::class)->tenant()->default_timezone)->toDateString())
                        ->required(),
                    TextInput::make('reason')->label('Alasan')->required()->minLength(5)->maxLength(200),
                ])
                ->action(function (Action $action, array $data): void {
                    $reversal = null;

                    DomainActions::forAction($action, function () use (&$reversal, $data): void {
                        $reversal = app(ReverseManualJournal::class)->handle($this->getRecord(), $data);
                    });

                    Notification::make()->success()->title('Jurnal dibalik')->body($reversal === null ? null : "Jurnal pembalik {$reversal->number} dibuat.")->send();
                    $this->refreshFormData([]);
                }),
        ];
    }

    private function isReversible(): bool
    {
        $entry = $this->getRecord();

        return $entry->event === JournalEvent::Manual
            && $entry->reversal_of_id === null
            && ! $entry->reversal()->exists()
            && User::current()->can('reverseManual', $entry);
    }
}
