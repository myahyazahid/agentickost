<?php

namespace App\Modules\Property\Filament\App\Resources\Properties\Pages;

use App\Modules\Property\Filament\App\Resources\Properties\PropertyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProperties extends ListRecords
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah properti'),
        ];
    }
}
