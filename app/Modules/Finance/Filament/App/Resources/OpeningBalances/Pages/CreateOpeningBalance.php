<?php

namespace App\Modules\Finance\Filament\App\Resources\OpeningBalances\Pages;

use App\Modules\Finance\Actions\SaveOpeningBalance;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\OpeningBalanceResource;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\Schemas\OpeningBalanceForm;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Starts a draft opening balance. Posting happens on the next page, after
 * the owner has checked the totals.
 */
class CreateOpeningBalance extends CreateRecord
{
    protected static string $resource = OpeningBalanceResource::class;

    protected static ?string $title = 'Catat saldo awal';

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return OpeningBalanceForm::run(fn () => app(SaveOpeningBalance::class)->handle(OpeningBalanceForm::toInput($data)));
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan draf');
    }

    protected function getCreatedNotificationTitle(): string
    {
        return 'Draf saldo awal tersimpan';
    }

    protected function getRedirectUrl(): string
    {
        return OpeningBalanceResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
