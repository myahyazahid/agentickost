<?php

namespace App\Modules\Finance\Filament\App\Resources\OpeningBalances\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Actions\DeleteOpeningBalance;
use App\Modules\Finance\Actions\PostOpeningBalance;
use App\Modules\Finance\Actions\SaveOpeningBalance;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\OpeningBalanceResource;
use App\Modules\Finance\Filament\App\Resources\OpeningBalances\Schemas\OpeningBalanceForm;
use App\Modules\Finance\Models\OpeningBalance;
use App\Support\Filament\DomainActions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * Edits a draft opening balance and posts it (FR-ONB-04, FR-ONB-05).
 *
 * @extends EditRecord<OpeningBalance>
 */
class EditOpeningBalance extends EditRecord
{
    protected static string $resource = OpeningBalanceResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Saldo awal per '.$this->getRecord()->cutoff_date->translatedFormat('j F Y');
    }

    public function getSubheading(): string
    {
        return 'Masih draf. Belum ada yang masuk ke tagihan, deposit, atau jurnal sampai Anda menekan Posting.';
    }

    protected function authorizeAccess(): void
    {
        $record = $this->getRecord();

        if (! $record->isDraft()) {
            $this->redirect(OpeningBalanceResource::getUrl('view', ['record' => $record]));

            return;
        }

        abort_unless(User::current()->can('update', $record), 403);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return OpeningBalanceForm::fromRecord($this->getRecord());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var OpeningBalance $record */
        return OpeningBalanceForm::run(fn () => app(SaveOpeningBalance::class)->handle(OpeningBalanceForm::toInput($data), $record));
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan draf');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('post')
                ->label('Posting saldo awal')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->visible(fn (): bool => User::current()->can('post', $this->getRecord()))
                ->requiresConfirmation()
                ->modalHeading('Posting saldo awal?')
                ->modalDescription('Perubahan di formulir disimpan lebih dulu. Setelah diposting, tunggakan menjadi tagihan saldo awal, deposit dan saldo kredit masuk ke catatan kontrak, dan jurnal pembuka dibuat. Saldo awal tidak bisa diubah lagi.')
                ->modalSubmitActionLabel('Posting')
                ->action(function (Action $action): void {
                    $this->handleRecordUpdate($this->getRecord(), $this->form->getState());

                    DomainActions::forAction($action, fn () => app(PostOpeningBalance::class)->handle($this->getRecord()));

                    Notification::make()->success()->title('Saldo awal diposting')->send();

                    $this->redirect(OpeningBalanceResource::getUrl('view', ['record' => $this->getRecord()]));
                }),
            Action::make('delete')
                ->label('Hapus draf')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (): bool => User::current()->can('delete', $this->getRecord()))
                ->requiresConfirmation()
                ->modalHeading('Hapus draf saldo awal?')
                ->modalSubmitActionLabel('Hapus draf')
                ->action(function (): void {
                    app(DeleteOpeningBalance::class)->handle($this->getRecord());

                    $this->redirect(OpeningBalanceResource::getUrl('index'));
                }),
        ];
    }

    protected function getSavedNotificationTitle(): string
    {
        return 'Draf saldo awal tersimpan';
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
