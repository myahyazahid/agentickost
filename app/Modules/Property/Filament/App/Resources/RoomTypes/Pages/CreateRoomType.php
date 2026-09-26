<?php

namespace App\Modules\Property\Filament\App\Resources\RoomTypes\Pages;

use App\Modules\Property\Actions\CreateRoomType as CreateRoomTypeAction;
use App\Modules\Property\Filament\App\Resources\RoomTypes\RoomTypeResource;
use App\Modules\Property\Models\Property;
use App\Support\Filament\DomainActions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateRoomType extends CreateRecord
{
    protected static string $resource = RoomTypeResource::class;

    protected static ?string $title = 'Tambah tipe kamar';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DomainActions::forForm(fn () => app(CreateRoomTypeAction::class)->handle(
            Property::query()->whereKey($data['property_id'])->firstOrFail(),
            Arr::except($data, 'property_id'),
        ));
    }

    protected function getRedirectUrl(): string
    {
        return RoomTypeResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
