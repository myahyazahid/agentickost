<?php

namespace App\Modules\Property\Filament\App\Resources\Rooms\Pages;

use App\Modules\Property\Actions\CreateRoom as CreateRoomAction;
use App\Modules\Property\Filament\App\Resources\Rooms\RoomResource;
use App\Modules\Property\Models\Property;
use App\Support\Filament\DomainActions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    protected static ?string $title = 'Tambah kamar';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DomainActions::forForm(fn () => app(CreateRoomAction::class)->handle(
            Property::query()->whereKey($data['property_id'])->firstOrFail(),
            Arr::except($data, 'property_id'),
        ));
    }
}
