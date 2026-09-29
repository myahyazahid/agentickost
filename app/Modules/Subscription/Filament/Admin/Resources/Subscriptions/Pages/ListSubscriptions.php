<?php

namespace App\Modules\Subscription\Filament\Admin\Resources\Subscriptions\Pages;

use App\Modules\Subscription\Filament\Admin\Resources\Subscriptions\SubscriptionResource;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;
}
