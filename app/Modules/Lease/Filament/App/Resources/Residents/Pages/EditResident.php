<?php

namespace App\Modules\Lease\Filament\App\Resources\Residents\Pages;

use App\Modules\Lease\Actions\UpdateResident;
use App\Modules\Lease\Filament\App\Resources\Residents\ResidentResource;
use App\Modules\Lease\Models\Resident;
use App\Support\Filament\DomainActions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends EditRecord<Resident>
 */
class EditResident extends EditRecord
{
    protected static string $resource = ResidentResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->full_name;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * The decrypted identity number is never put in the form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['identity_number']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (($data['identity_documents'] ?? []) === []) {
            unset($data['identity_documents']);
        }

        return DomainActions::forForm(fn () => app(UpdateResident::class)->handle($this->getRecord(), $data));
    }
}
