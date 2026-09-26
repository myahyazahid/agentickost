<?php

namespace App\Modules\Lease\Filament\App\Resources\Residents\Pages;

use App\Modules\Lease\Filament\App\Resources\Residents\ResidentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResidents extends ListRecords
{
    protected static string $resource = ResidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah penghuni'),
        ];
    }
}
