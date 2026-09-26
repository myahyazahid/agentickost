<?php

namespace App\Modules\Lease\Filament\App\Resources\Residents\Pages;

use App\Modules\Lease\Actions\CreateResident as CreateResidentAction;
use App\Modules\Lease\Filament\App\Resources\Residents\ResidentResource;
use App\Support\Filament\DomainActions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateResident extends CreateRecord
{
    protected static string $resource = ResidentResource::class;

    protected static ?string $title = 'Tambah penghuni';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DomainActions::forForm(fn () => app(CreateResidentAction::class)->handle($data));
    }
}
