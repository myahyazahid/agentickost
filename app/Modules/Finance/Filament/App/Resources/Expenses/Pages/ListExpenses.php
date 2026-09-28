<?php

namespace App\Modules\Finance\Filament\App\Resources\Expenses\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Filament\App\Resources\Expenses\ExpenseResource;
use App\Modules\Finance\Models\Expense;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat pengeluaran')
                ->visible(fn (): bool => User::current()->can('create', Expense::class)),
        ];
    }
}
