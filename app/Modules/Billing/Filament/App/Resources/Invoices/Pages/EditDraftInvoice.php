<?php

namespace App\Modules\Billing\Filament\App\Resources\Invoices\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Actions\UpdateDraftInvoice;
use App\Modules\Billing\Enums\InvoiceItemType;
use App\Modules\Billing\Filament\App\Resources\Invoices\InvoiceResource;
use App\Modules\Billing\Filament\App\Resources\Invoices\Schemas\AdhocInvoiceForm;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Billing\States\Invoice\Draft;
use App\Support\Filament\DomainActions;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends EditRecord<Invoice>
 */
class EditDraftInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Ubah draf tagihan';
    }

    public function form(Schema $schema): Schema
    {
        return AdhocInvoiceForm::configure($schema, creating: false);
    }

    protected function authorizeAccess(): void
    {
        $invoice = $this->getRecord();

        abort_unless($invoice->status->equals(Draft::class) && User::current()->can('update', $invoice), 403);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $invoice = $this->getRecord();

        return [
            'due_date' => $invoice->due_date->toDateString(),
            'items' => $invoice->items()->get()->map(fn (InvoiceItem $item): array => [
                'type' => $item->type->value,
                'description' => $item->description,
                'amount' => $item->type === InvoiceItemType::Discount ? -$item->amount : $item->amount,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DomainActions::forForm(fn (): Invoice => app(UpdateDraftInvoice::class)->handle($this->getRecord(), [
            ...$data,
            'items' => array_values($data['items'] ?? []),
        ]));
    }

    protected function getRedirectUrl(): string
    {
        return InvoiceResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
