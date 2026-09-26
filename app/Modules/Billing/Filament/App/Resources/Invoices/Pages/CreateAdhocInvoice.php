<?php

namespace App\Modules\Billing\Filament\App\Resources\Invoices\Pages;

use App\Modules\Billing\Actions\CreateAdhocInvoice as CreateAdhocInvoiceAction;
use App\Modules\Billing\Filament\App\Resources\Invoices\InvoiceResource;
use App\Modules\Billing\Filament\App\Resources\Invoices\Schemas\AdhocInvoiceForm;
use App\Modules\Property\Models\Property;
use App\Support\Filament\DomainActions;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateAdhocInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static ?string $title = 'Buat tagihan manual';

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return AdhocInvoiceForm::configure($schema, creating: true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $property = Property::query()->whereKey($data['property_id'] ?? null)->first();

        return DomainActions::forForm(function () use ($property, $data): Model {
            abort_if($property === null, 404);

            return app(CreateAdhocInvoiceAction::class)->handle($property, [
                ...$data,
                'items' => array_values($data['items'] ?? []),
            ]);
        });
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan');
    }

    protected function getRedirectUrl(): string
    {
        return InvoiceResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
