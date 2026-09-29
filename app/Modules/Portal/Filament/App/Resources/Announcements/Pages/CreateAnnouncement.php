<?php

namespace App\Modules\Portal\Filament\App\Resources\Announcements\Pages;

use App\Modules\Portal\Actions\SaveAnnouncement;
use App\Modules\Portal\Filament\App\Resources\Announcements\AnnouncementResource;
use App\Support\Filament\DomainActions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected static ?string $title = 'Tulis pengumuman';

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DomainActions::forForm(fn () => app(SaveAnnouncement::class)->handle($data));
    }

    protected function getRedirectUrl(): string
    {
        return AnnouncementResource::getUrl('index');
    }
}
