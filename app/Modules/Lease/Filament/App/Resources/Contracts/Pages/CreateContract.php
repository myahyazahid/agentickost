<?php

namespace App\Modules\Lease\Filament\App\Resources\Contracts\Pages;

use App\Modules\Lease\Actions\CreateContract as CreateContractAction;
use App\Modules\Lease\Filament\App\Resources\Contracts\ContractResource;
use App\Support\Filament\DomainActions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;

    protected static ?string $title = 'Buat kontrak';

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DomainActions::forForm(fn () => app(CreateContractAction::class)->handle($data));
    }

    protected function getRedirectUrl(): string
    {
        return ContractResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
