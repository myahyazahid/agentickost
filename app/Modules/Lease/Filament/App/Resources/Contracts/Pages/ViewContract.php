<?php

namespace App\Modules\Lease\Filament\App\Resources\Contracts\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Actions\ActivateContract;
use App\Modules\Lease\Actions\CancelNotice;
use App\Modules\Lease\Actions\DeleteDraftContract;
use App\Modules\Lease\Actions\GiveNotice;
use App\Modules\Lease\Actions\RenewContract;
use App\Modules\Lease\Actions\TerminateContract;
use App\Modules\Lease\Actions\UpdateContractPayer;
use App\Modules\Lease\Enums\PayerRelation;
use App\Modules\Lease\Filament\App\Resources\Contracts\ContractResource;
use App\Modules\Lease\Filament\App\Resources\Contracts\Schemas\ContractForm;
use App\Modules\Lease\Filament\App\Resources\Contracts\Schemas\StayActions;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Lease\States\Contract\Completed;
use App\Modules\Lease\States\Contract\Draft;
use App\Modules\Lease\States\Contract\Notice;
use App\Modules\Lease\States\Contract\Terminated;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Support\RoomPricing;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyInput;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @extends ViewRecord<Contract>
 */
class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    public function getTitle(): string|Htmlable
    {
        $contract = $this->getRecord();

        return $contract->number !== null ? "Kontrak {$contract->number}" : 'Draf kontrak kamar '.$contract->room?->number;
    }

    protected function getHeaderActions(): array
    {
        $contract = fn (): Contract => $this->getRecord();
        $run = fn (Action $action, \Closure $callback, string $success) => $this->run($action, $callback, $success);

        return [
            StayActions::checkIn($contract, $run),
            $this->activateAction(),
            $this->giveNoticeAction(),
            StayActions::checkOut($contract, $run),
            StayActions::finalizeSettlement($contract, $run),
            $this->renewAction(),
            ActionGroup::make([
                StayActions::moveRoom($contract, $run),
                Action::make('editDraft')
                    ->label('Ubah draf')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (): string => ContractResource::getUrl('edit', ['record' => $this->getRecord()]))
                    ->visible(fn (): bool => $this->isDraft() && $this->canManage()),
                $this->cancelNoticeAction(),
                $this->changePayerAction(),
                Action::make('pdf')
                    ->label('Unduh PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (): string => route('lease.contracts.pdf', ['contract' => $this->getRecord()->id]), shouldOpenInNewTab: true),
                $this->terminateAction(),
                $this->deleteDraftAction(),
            ])->label('Lainnya')->button()->color('gray'),
        ];
    }

    private function activateAction(): Action
    {
        return Action::make('activate')
            ->label('Aktifkan kontrak')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->requiresConfirmation()
            ->modalHeading('Aktifkan kontrak ini?')
            ->modalDescription(fn (): string => $this->getRecord()->renewed_from_contract_id === null
                ? 'Kontrak mendapat nomor dan kamar ditandai terisi. Syarat kontrak tidak bisa diubah lagi.'
                : 'Kontrak sebelumnya selesai dan perpanjangan ini mulai berlaku.')
            ->modalSubmitActionLabel('Aktifkan')
            ->visible(fn (): bool => $this->isDraft() && $this->canManage())
            ->action(fn (Action $action) => $this->run($action, fn () => app(ActivateContract::class)->handle($this->getRecord()), 'Kontrak aktif'));
    }

    private function giveNoticeAction(): Action
    {
        return Action::make('giveNotice')
            ->label('Catat rencana keluar')
            ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
            ->color('warning')
            ->visible(fn (): bool => $this->getRecord()->status->equals(Active::class) && $this->canManage())
            ->schema([
                DatePicker::make('notice_given_on')->label('Diberitahukan pada')->default(now())->maxDate(now())->required(),
                DatePicker::make('planned_move_out_on')
                    ->label('Rencana tanggal keluar')
                    ->helperText(fn (): string => 'Masa pemberitahuan properti ini '.$this->getRecord()->property()->firstOrFail()->resolvedSettings()->notice_days.' hari. Bila kurang, penalti kontrak diusulkan saat check-out.')
                    ->required(),
            ])
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(GiveNotice::class)->handle($this->getRecord(), $data), 'Rencana keluar dicatat'));
    }

    private function cancelNoticeAction(): Action
    {
        return Action::make('cancelNotice')
            ->label('Batalkan rencana keluar')
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->modalDescription('Penghuni tetap tinggal dan kamar kembali berstatus terisi.')
            ->visible(fn (): bool => $this->getRecord()->status->equals(Notice::class) && $this->canManage())
            ->action(fn (Action $action) => $this->run($action, fn () => app(CancelNotice::class)->handle($this->getRecord()), 'Rencana keluar dibatalkan'));
    }

    private function renewAction(): Action
    {
        return Action::make('renew')
            ->label('Perpanjang')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => $this->getRecord()->status->equals(Active::class)
                && ! $this->getRecord()->renewal()->exists()
                && $this->canManage())
            ->fillForm(fn (): array => [
                'rental_period' => $this->getRecord()->rental_period->value,
                'rent_amount' => $this->currentRoomPrice() ?? $this->getRecord()->rent_amount,
                'deposit_amount' => $this->getRecord()->deposit_amount,
            ])
            ->schema([
                Select::make('rental_period')->label('Periode bayar')->options(RentalPeriod::class)->required(),
                MoneyInput::make('rent_amount')
                    ->label('Sewa per periode')
                    ->helperText('Terisi harga kamar saat ini. Ubah bila disepakati lain.')
                    ->required(),
                MoneyInput::make('deposit_amount')->label('Deposit')->required(),
                DatePicker::make('start_date')
                    ->label('Perpanjangan mulai')
                    ->helperText('Kontrak ini tidak punya tanggal selesai, jadi berakhir sehari sebelumnya.')
                    ->visible(fn (): bool => $this->getRecord()->end_date === null)
                    ->required(fn (): bool => $this->getRecord()->end_date === null),
                DatePicker::make('end_date')->label('Perpanjangan selesai')->helperText('Kosongkan bila berjalan sampai diakhiri.'),
            ])
            ->action(function (Action $action, array $data): void {
                DomainActions::forAction($action, function () use ($data): void {
                    $renewal = app(RenewContract::class)->handle($this->getRecord(), $data);

                    Notification::make()->success()->title('Draf perpanjangan dibuat')
                        ->body('Aktif otomatis pada '.$renewal->start_date->translatedFormat('j F Y').'.')
                        ->send();

                    $this->redirect(ContractResource::getUrl('view', ['record' => $renewal]));
                });
            });
    }

    private function terminateAction(): Action
    {
        return Action::make('terminate')
            ->label('Putus kontrak')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (): bool => $this->getRecord()->isRunning()
                && ! $this->getRecord()->renewal()->exists()
                && $this->canManage())
            ->modalDescription('Kontrak berakhir lebih awal. Tagihan akhir dan deposit diselesaikan saat check-out.')
            ->fillForm(fn (): array => [
                'ended_on' => now()->toDateString(),
                'termination_penalty_amount' => $this->getRecord()->early_termination_penalty_amount,
            ])
            ->schema([
                DatePicker::make('ended_on')->label('Berakhir pada')->required(),
                Textarea::make('termination_reason')->label('Alasan')->required(),
                MoneyInput::make('termination_penalty_amount')
                    ->label('Denda pemutusan')
                    ->helperText('Terisi dari kontrak. Kosongkan bila tidak dikenakan denda.'),
            ])
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(TerminateContract::class)->handle($this->getRecord(), $data), 'Kontrak diputus'));
    }

    private function changePayerAction(): Action
    {
        return Action::make('changePayer')
            ->label('Ubah pembayar')
            ->icon(Heroicon::OutlinedUser)
            ->visible(fn (): bool => ! $this->getRecord()->status->equals(Completed::class, Terminated::class)
                && $this->canManage())
            ->fillForm(function (): array {
                $contract = $this->getRecord();
                $payer = $contract->payer;
                $isSelf = $payer?->relation === PayerRelation::Self;

                return [
                    'payer' => $isSelf ? 'self' : 'other',
                    'payer_name' => $isSelf ? null : $payer?->name,
                    'payer_phone' => $isSelf ? null : $payer?->phone,
                    'payer_email' => $isSelf ? null : $payer?->email,
                    'payer_relation' => $isSelf ? null : $payer?->relation->value,
                    'notify_resident' => $contract->notify_resident,
                    'notify_payer' => $contract->notify_payer,
                ];
            })
            ->schema([
                Radio::make('payer')
                    ->label('Siapa yang membayar?')
                    ->options(['self' => 'Penghuni utama', 'other' => 'Pihak lain'])
                    ->required()
                    ->live(),
                ...ContractForm::otherPayerFields(),
                Toggle::make('notify_resident')->label('Kirim tagihan ke penghuni'),
            ])
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(UpdateContractPayer::class)->handle($this->getRecord(), $data), 'Pembayar diperbarui'));
    }

    private function deleteDraftAction(): Action
    {
        return Action::make('deleteDraft')
            ->label('Hapus draf')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->isDraft() && $this->canManage())
            ->action(function (Action $action): void {
                DomainActions::forAction($action, fn () => app(DeleteDraftContract::class)->handle($this->getRecord()));

                $this->redirect(ContractResource::getUrl('index'));
            });
    }

    /**
     * @param  \Closure(): mixed  $callback
     */
    private function run(Action $action, \Closure $callback, string $success): void
    {
        DomainActions::forAction($action, $callback);

        $this->getRecord()->refresh();

        Notification::make()->success()->title($success)->send();
    }

    private function isDraft(): bool
    {
        return $this->getRecord()->status->equals(Draft::class);
    }

    private function canManage(): bool
    {
        return User::current()->can('update', $this->getRecord());
    }

    private function currentRoomPrice(): ?int
    {
        $contract = $this->getRecord();
        $room = $contract->room;

        return $room === null ? null : app(RoomPricing::class)->priceFor(
            $room,
            $contract->rental_period,
            CarbonImmutable::parse($contract->end_date ?? now())->addDay(),
        );
    }
}
