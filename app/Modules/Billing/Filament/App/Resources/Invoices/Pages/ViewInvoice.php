<?php

namespace App\Modules\Billing\Filament\App\Resources\Invoices\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Actions\DeleteDraftInvoice;
use App\Modules\Billing\Actions\IssueCreditNote;
use App\Modules\Billing\Actions\IssueInvoice;
use App\Modules\Billing\Actions\VoidInvoice;
use App\Modules\Billing\Actions\WaivePenalties;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Filament\App\Resources\Invoices\InvoiceResource;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Draft;
use App\Modules\Billing\States\Invoice\Issued;
use App\Modules\Billing\States\Invoice\Voided;
use App\Modules\Billing\Support\InvoiceDocument;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Payment\Actions\ApplyCredit;
use App\Modules\Payment\Actions\ApplyDepositToInvoice;
use App\Modules\Payment\Filament\App\Resources\Payments\PaymentResource;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Support\CreditLedger;
use App\Modules\Property\Enums\AllocationCategory;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyInput;
use App\Support\Money\Rupiah;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @extends ViewRecord<Invoice>
 */
class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string|Htmlable
    {
        $invoice = $this->getRecord();

        return $invoice->number !== null ? "Tagihan {$invoice->number}" : 'Draf tagihan';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->issueAction(),
            $this->recordPaymentAction(),
            $this->shareAction(),
            ActionGroup::make([
                Action::make('editDraft')
                    ->label('Ubah draf')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (): string => InvoiceResource::getUrl('edit', ['record' => $this->getRecord()]))
                    ->visible(fn (): bool => $this->isDraft() && $this->can('update')),
                Action::make('pdf')
                    ->label('Unduh PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (): string => route('billing.invoices.pdf', ['invoice' => $this->getRecord()->id]), shouldOpenInNewTab: true),
                $this->applyCreditAction(),
                $this->applyDepositAction(),
                $this->creditAction(),
                $this->waivePenaltiesAction(),
                $this->voidAction(),
                $this->deleteDraftAction(),
            ])->label('Lainnya')->button()->color('gray'),
        ];
    }

    private function issueAction(): Action
    {
        return Action::make('issue')
            ->label('Terbitkan')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->requiresConfirmation()
            ->modalHeading('Terbitkan tagihan ini?')
            ->modalDescription('Tagihan mendapat nomor dan tidak bisa diubah lagi. Koreksi setelahnya lewat void atau nota kredit.')
            ->modalSubmitActionLabel('Terbitkan')
            ->visible(fn (): bool => $this->isDraft() && $this->can('update'))
            ->action(fn (Action $action) => $this->run($action, fn () => app(IssueInvoice::class)->handle($this->getRecord()), 'Tagihan terbit'));
    }

    private function recordPaymentAction(): Action
    {
        return Action::make('recordPayment')
            ->label('Catat pembayaran')
            ->icon(Heroicon::OutlinedBanknotes)
            ->visible(fn (): bool => $this->getRecord()->status->isOpen() && User::current()->can('create', Payment::class))
            ->url(fn (): string => PaymentResource::getUrl('create', ['kontrak' => $this->getRecord()->contract_id]));
    }

    private function applyCreditAction(): Action
    {
        return Action::make('applyCredit')
            ->label('Pakai saldo kredit')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->visible(fn (): bool => $this->getRecord()->status->isOpen()
                && $this->creditBalance() > 0
                && User::current()->can('applyCreditIn', [Payment::class, $this->getRecord()->property()->firstOrFail()]))
            ->requiresConfirmation()
            ->modalHeading('Pakai saldo kredit?')
            ->modalDescription(fn (): string => 'Saldo kredit kontrak ini '.Rupiah::format($this->creditBalance()).'. Saldo dipakai untuk tagihan kontrak yang belum lunas, mulai dari yang paling lama.')
            ->modalSubmitActionLabel('Pakai saldo')
            ->action(fn (Action $action) => $this->run($action, fn () => app(ApplyCredit::class)->handle($this->getRecord()->contract()->firstOrFail()), 'Saldo kredit dipakai'));
    }

    private function applyDepositAction(): Action
    {
        return Action::make('applyDeposit')
            ->label('Bayar dari deposit')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->visible(fn (): bool => $this->getRecord()->status->isOpen()
                && $this->getRecord()->contract_id !== null
                && DepositLedger::balance($this->getRecord()->contract_id) > 0
                && User::current()->can('manageFor', [DepositTransaction::class, $this->getRecord()->contract()->firstOrFail()]))
            ->modalDescription(fn (): string => 'Deposit dipegang '.Rupiah::format(DepositLedger::balance((string) $this->getRecord()->contract_id)).'. Deposit hanya dipakai untuk tagihan berjalan atas persetujuan owner.')
            ->schema([
                MoneyInput::make('amount')->label('Jumlah')->required()->minValue(1),
                Textarea::make('reason')->label('Alasan')->placeholder('Misal: disetujui owner karena penghuni akan keluar bulan ini')->required()->minLength(5),
            ])
            ->modalSubmitActionLabel('Bayar dari deposit')
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(ApplyDepositToInvoice::class)->handle($this->getRecord(), $data), 'Tagihan dibayar dari deposit'));
    }

    private function creditBalance(): int
    {
        $contractId = $this->getRecord()->contract_id;

        return $contractId === null ? 0 : CreditLedger::balance($contractId);
    }

    private function shareAction(): Action
    {
        return Action::make('share')
            ->label('Bagikan')
            ->icon(Heroicon::OutlinedShare)
            ->color('gray')
            ->visible(fn (): bool => InvoiceDocument::canShare($this->getRecord()))
            ->modalHeading('Tautan tagihan')
            ->modalDescription('Kirim tautan ini ke pembayar lewat WhatsApp atau email. Pembayar bisa membuka PDF tanpa login selama '.InvoiceDocument::SHARE_DAYS.' hari.')
            ->fillForm(fn (): array => ['url' => app(InvoiceDocument::class)->shareUrl($this->getRecord())])
            ->schema([
                TextInput::make('url')
                    ->hiddenLabel()
                    ->readOnly()
                    ->copyable(copyMessage: 'Tautan disalin'),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup');
    }

    private function creditAction(): Action
    {
        return Action::make('credit')
            ->label('Buat nota kredit')
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->visible(fn (): bool => ! $this->getRecord()->status->equals(Draft::class, Voided::class) && $this->can('credit'))
            ->modalDescription('Mengurangi tagihan tanpa mengubahnya, misalnya potongan karena kamar mati air.')
            ->schema([
                Select::make('allocation_category')
                    ->label('Bagian yang dikurangi')
                    ->options(fn (): array => $this->creditableOptions())
                    ->required()
                    ->native(false),
                MoneyInput::make('amount')->label('Jumlah')->required()->minValue(1),
                Textarea::make('reason')->label('Alasan')->required()->minLength(5),
            ])
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(IssueCreditNote::class)->handle($this->getRecord(), $data), 'Nota kredit dibuat'));
    }

    private function waivePenaltiesAction(): Action
    {
        return Action::make('waivePenalties')
            ->label('Hapus denda')
            ->icon(Heroicon::OutlinedMinusCircle)
            ->visible(fn (): bool => $this->getRecord()->status->isOpen()
                && $this->getRecord()->penalty_amount > 0
                && $this->can('waivePenalty'))
            ->modalDescription(fn (): string => 'Denda '.Rupiah::format($this->getRecord()->penalty_amount).' dihapus dan alasannya dicatat di log audit. Bila tagihan tetap belum dibayar, denda hari berikutnya tetap dihitung.')
            ->schema([
                Textarea::make('reason')->label('Alasan')->required()->minLength(5),
            ])
            ->modalSubmitActionLabel('Hapus denda')
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(WaivePenalties::class)->handle($this->getRecord(), $data), 'Denda dihapus'));
    }

    private function voidAction(): Action
    {
        return Action::make('void')
            ->label('Batalkan (void)')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (): bool => $this->getRecord()->status->equals(Issued::class)
                && $this->getRecord()->paid_amount === 0
                && $this->getRecord()->credited_amount === 0
                && $this->can('void'))
            ->modalHeading('Batalkan tagihan ini?')
            ->modalDescription(fn (): string => $this->getRecord()->type === InvoiceType::Rent
                ? 'Tagihan tetap tersimpan dengan status dibatalkan. Bila ini tagihan sewa terakhir kontraknya, periode yang sama diterbitkan ulang pada proses berikutnya dengan data terbaru.'
                : 'Tagihan tetap tersimpan dengan status dibatalkan dan tidak perlu dibayar.')
            ->schema([
                Textarea::make('reason')->label('Alasan')->required()->minLength(5),
            ])
            ->modalSubmitActionLabel('Batalkan tagihan')
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(VoidInvoice::class)->handle($this->getRecord(), $data), 'Tagihan dibatalkan'));
    }

    private function deleteDraftAction(): Action
    {
        return Action::make('deleteDraft')
            ->label('Hapus draf')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->isDraft() && $this->can('delete'))
            ->action(function (Action $action): void {
                DomainActions::forAction($action, fn () => app(DeleteDraftInvoice::class)->handle($this->getRecord()));

                $this->redirect(InvoiceResource::getUrl('index'));
            });
    }

    /**
     * @return array<string, string>
     */
    private function creditableOptions(): array
    {
        $options = [];

        foreach (AllocationCategory::cases() as $category) {
            $left = IssueCreditNote::creditable($this->getRecord(), $category);

            if ($left > 0) {
                $options[$category->value] = "{$category->getLabel()} (paling banyak ".Rupiah::format($left).')';
            }
        }

        return $options;
    }

    /**
     * @param  Closure(): mixed  $callback
     */
    private function run(Action $action, Closure $callback, string $success): void
    {
        DomainActions::forAction($action, $callback);

        $this->getRecord()->refresh();

        Notification::make()->success()->title($success)->send();
    }

    private function isDraft(): bool
    {
        return $this->getRecord()->status->equals(Draft::class);
    }

    private function can(string $ability): bool
    {
        return User::current()->can($ability, $this->getRecord());
    }
}
