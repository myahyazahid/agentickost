<?php

namespace App\Modules\Billing\Filament\App\Resources\MeterReadings\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Filament\App\Resources\MeterReadings\MeterReadingResource;
use App\Modules\Billing\Models\MeterReading;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMeterReadings extends ListRecords
{
    protected static string $resource = MeterReadingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat meteran')
                ->icon(Heroicon::OutlinedCamera)
                ->visible(fn (): bool => User::current()->can('create', MeterReading::class)),
        ];
    }
}
