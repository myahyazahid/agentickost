<?php

namespace App\Modules\Billing\Filament\App\Resources\Invoices\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Filament\App\Resources\Invoices\InvoiceResource;
use App\Modules\Billing\Models\Invoice;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat tagihan manual')
                ->visible(fn (): bool => User::current()->can('create', Invoice::class)),
        ];
    }
}
