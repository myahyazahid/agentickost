<?php

namespace App\Modules\Finance\Filament\App\Resources\BankAccounts\Pages;

use App\Modules\Finance\Actions\CreateBankAccount as CreateBankAccountAction;
use App\Modules\Finance\Filament\App\Resources\BankAccounts\BankAccountResource;
use App\Support\Filament\DomainActions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBankAccount extends CreateRecord
{
    protected static string $resource = BankAccountResource::class;

    protected static ?string $title = 'Tambah rekening tujuan';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DomainActions::forForm(fn () => app(CreateBankAccountAction::class)->handle($data));
    }
}
