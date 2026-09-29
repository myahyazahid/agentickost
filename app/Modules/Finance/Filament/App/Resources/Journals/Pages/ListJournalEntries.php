<?php

namespace App\Modules\Finance\Filament\App\Resources\Journals\Pages;

use App\Modules\Finance\Filament\App\Resources\Journals\JournalEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJournalEntries extends ListRecords
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Jurnal manual'),
        ];
    }
}
