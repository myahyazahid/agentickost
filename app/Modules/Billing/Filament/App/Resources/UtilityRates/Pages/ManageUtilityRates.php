<?php

namespace App\Modules\Billing\Filament\App\Resources\UtilityRates\Pages;

use App\Modules\Billing\Filament\App\Resources\UtilityRates\UtilityRateResource;
use Filament\Resources\Pages\ManageRecords;

class ManageUtilityRates extends ManageRecords
{
    protected static string $resource = UtilityRateResource::class;

    protected function getHeaderActions(): array
    {
        return [UtilityRateResource::setRateAction()];
    }
}
