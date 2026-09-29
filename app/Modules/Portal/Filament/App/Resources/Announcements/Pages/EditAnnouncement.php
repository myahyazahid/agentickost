<?php

namespace App\Modules\Portal\Filament\App\Resources\Announcements\Pages;

use App\Modules\Portal\Actions\DeleteAnnouncement;
use App\Modules\Portal\Actions\SaveAnnouncement;
use App\Modules\Portal\Filament\App\Resources\Announcements\AnnouncementResource;
use App\Modules\Portal\Models\Announcement;
use App\Support\Filament\DomainActions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends EditRecord<Announcement>
 */
class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->label('Hapus')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Hapus pengumuman ini?')
                ->modalDescription('Pengumuman hilang dari portal penghuni.')
                ->action(function (Action $action): void {
                    DomainActions::forAction($action, fn () => app(DeleteAnnouncement::class)->handle($this->getRecord()));

                    Notification::make()->success()->title('Pengumuman dihapus')->send();
                    $this->redirect(AnnouncementResource::getUrl('index'));
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'publish' => $this->getRecord()->published_at !== null];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DomainActions::forForm(fn () => app(SaveAnnouncement::class)->handle($data, $this->getRecord()));
    }

    protected function getRedirectUrl(): string
    {
        return AnnouncementResource::getUrl('index');
    }
}
