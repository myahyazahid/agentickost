<?php

namespace App\Modules\Property\Filament\App\Resources\Rooms\Pages;

use App\Modules\Property\Filament\App\Resources\Rooms\RoomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRooms extends ListRecords
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah kamar'),
        ];
    }
}
