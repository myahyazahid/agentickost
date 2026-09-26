<?php

namespace App\Modules\Finance\Filament\App\Resources\BankAccounts\Pages;

use App\Modules\Finance\Actions\UpdateBankAccount;
use App\Modules\Finance\Filament\App\Resources\BankAccounts\BankAccountResource;
use App\Modules\Finance\Models\BankAccount;
use App\Support\Filament\DomainActions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends EditRecord<BankAccount>
 */
class EditBankAccount extends EditRecord
{
    protected static string $resource = BankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DomainActions::forForm(fn () => app(UpdateBankAccount::class)->handle($this->getRecord(), $data));
    }
}
