<?php

namespace App\Modules\Property\Filament\App\Resources\Properties\Pages;

use App\Modules\Property\Actions\CreateProperty as CreatePropertyAction;
use App\Modules\Property\Filament\App\Resources\Properties\PropertyResource;
use App\Support\Filament\DomainActions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProperty extends CreateRecord
{
    protected static string $resource = PropertyResource::class;

    protected static ?string $title = 'Tambah properti';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DomainActions::forForm(fn () => app(CreatePropertyAction::class)->handle($data));
    }
}
