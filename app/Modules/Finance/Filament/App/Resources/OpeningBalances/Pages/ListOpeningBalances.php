<?php

namespace App\Modules\Finance\Filament\App\Resources\OpeningBalances\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\OpeningBalanceResource;
use App\Modules\Finance\Models\OpeningBalance;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOpeningBalances extends ListRecords
{
    protected static string $resource = OpeningBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat saldo awal')
                ->visible(fn (): bool => User::current()->can('create', OpeningBalance::class)),
        ];
    }
}
