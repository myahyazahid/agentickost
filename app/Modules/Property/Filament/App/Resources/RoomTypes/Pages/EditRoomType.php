<?php

namespace App\Modules\Property\Filament\App\Resources\RoomTypes\Pages;

use App\Modules\Property\Actions\DeleteRoomType;
use App\Modules\Property\Actions\UpdateRoomType;
use App\Modules\Property\Filament\App\Resources\RoomTypes\RoomTypeResource;
use App\Modules\Property\Models\RoomType;
use App\Support\Filament\DomainActions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends EditRecord<RoomType>
 */
class EditRoomType extends EditRecord
{
    protected static string $resource = RoomTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (DeleteAction $action, RoomType $record): bool {
                    DomainActions::forAction($action, fn () => app(DeleteRoomType::class)->handle($record));

                    return true;
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DomainActions::forForm(fn () => app(UpdateRoomType::class)->handle($this->getRecord(), $data));
    }
}
