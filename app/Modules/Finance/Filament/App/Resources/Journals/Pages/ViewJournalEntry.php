<?php

namespace App\Modules\Finance\Filament\App\Resources\Journals\Pages;

use App\Modules\Finance\Filament\App\Resources\Journals\JournalEntryResource;
use App\Modules\Finance\Models\JournalEntry;
use Filament\Resources\Pages\ViewRecord;
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
}
