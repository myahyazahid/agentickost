<?php

namespace App\Modules\Property\Filament\App\Resources\Rooms\Pages;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Property\Actions\DeleteRoom;
use App\Modules\Property\Actions\UpdateRoom;
use App\Modules\Property\Filament\App\Resources\Rooms\RoomResource;
use App\Modules\Property\Models\Room;
use App\Support\Filament\DomainActions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends EditRecord<Room>
 */
class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    public function getTitle(): string|Htmlable
    {
        return "Kamar {$this->getRecord()->number}";
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (DeleteAction $action, Room $record): bool {
                    DomainActions::forAction($action, fn () => app(DeleteRoom::class)->handle($record));

                    return true;
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['photos'] = $this->getRecord()->attachmentPaths(AttachmentCollection::Photo);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DomainActions::forForm(fn () => app(UpdateRoom::class)->handle($this->getRecord(), $data));
    }
}
