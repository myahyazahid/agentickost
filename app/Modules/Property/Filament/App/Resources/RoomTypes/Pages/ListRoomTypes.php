<?php

namespace App\Modules\Property\Filament\App\Resources\RoomTypes\Pages;

use App\Modules\Property\Filament\App\Resources\RoomTypes\RoomTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoomTypes extends ListRecords
{
    protected static string $resource = RoomTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah tipe kamar'),
        ];
    }
}
