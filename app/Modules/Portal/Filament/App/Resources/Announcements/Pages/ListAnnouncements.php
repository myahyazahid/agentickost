<?php

namespace App\Modules\Portal\Filament\App\Resources\Announcements\Pages;

use App\Modules\Portal\Filament\App\Resources\Announcements\AnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tulis pengumuman'),
        ];
    }
}
