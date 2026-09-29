<?php

namespace App\Modules\Subscription\Filament\Admin\Resources\SubscriptionInvoices\Pages;

use App\Modules\Subscription\Filament\Admin\Resources\SubscriptionInvoices\SubscriptionInvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionInvoices extends ListRecords
{
    protected static string $resource = SubscriptionInvoiceResource::class;
}
