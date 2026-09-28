<?php

namespace App\Modules\Payment\Filament\App\Resources\Payments\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Payment\Actions\RejectPayment;
use App\Modules\Payment\Actions\ReversePayment;
use App\Modules\Payment\Actions\VerifyPayment;
use App\Modules\Payment\Filament\App\Resources\Payments\PaymentResource;
use App\Modules\Payment\Filament\PaymentFields;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\States\Payment\Pending;
use App\Modules\Payment\States\Payment\Verified;
use App\Modules\Payment\Support\ReceiptDocument;
use App\Support\Filament\DomainActions;
use App\Support\Money\Rupiah;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @extends ViewRecord<Payment>
 */
class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string|Htmlable
    {
        $payment = $this->getRecord();

        return $payment->receipt_number !== null
            ? "Pembayaran {$payment->receipt_number}"
            : 'Pembayaran '.Rupiah::format($payment->amount);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->verifyAction(),
            $this->shareAction(),
            ActionGroup::make([
                Action::make('receipt')
                    ->label('Unduh kuitansi')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->visible(fn (): bool => ReceiptDocument::exists($this->getRecord()))
                    ->url(fn (): string => route('payment.receipts.pdf', ['payment' => $this->getRecord()->id]), shouldOpenInNewTab: true),
                $this->rejectAction(),
                $this->reverseAction(),
            ])->label('Lainnya')->button()->color('gray'),
        ];
    }

    private function verifyAction(): Action
    {
        return Action::make('verify')
            ->label('Verifikasi')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->visible(fn (): bool => $this->isPending() && $this->can('verify'))
            ->modalHeading('Verifikasi pembayaran ini?')
            ->modalDescription(fn (): string => 'Pastikan '.Rupiah::format($this->getRecord()->amount).' sudah masuk sesuai bukti. Setelah diverifikasi, pembayaran melunasi tagihan dan mendapat nomor kuitansi.')
            ->schema([
                Text::make(fn (): string => PaymentFields::contractSummary($this->getRecord()->contract_id)),
                ...PaymentFields::allocation(fn (): ?string => $this->getRecord()->contract_id),
            ])
            ->modalSubmitActionLabel('Verifikasi')
            ->action(fn (Action $action, array $data) => $this->run(
                $action,
                fn () => app(VerifyPayment::class)->handle($this->getRecord(), PaymentFields::allocationInput($data)),
                'Pembayaran terverifikasi',
            ));
    }

    private function shareAction(): Action
    {
        return Action::make('share')
            ->label('Bagikan kuitansi')
            ->icon(Heroicon::OutlinedShare)
            ->color('gray')
            ->visible(fn (): bool => ReceiptDocument::exists($this->getRecord()))
            ->modalHeading('Tautan kuitansi')
            ->modalDescription('Kirim tautan ini ke pembayar. Kuitansi bisa dibuka tanpa login selama '.ReceiptDocument::SHARE_DAYS.' hari.')
            ->fillForm(fn (): array => ['url' => app(ReceiptDocument::class)->shareUrl($this->getRecord())])
            ->schema([
                TextInput::make('url')->hiddenLabel()->readOnly()->copyable(copyMessage: 'Tautan disalin'),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup');
    }

    private function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Tolak')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (): bool => $this->isPending() && $this->can('verify'))
            ->modalHeading('Tolak pembayaran ini?')
            ->modalDescription('Tagihan tetap belum dibayar. Alasannya disimpan agar bisa disampaikan ke pembayar.')
            ->schema([
                Textarea::make('reason')->label('Alasan')->placeholder('Misal: dana tidak masuk ke rekening')->required()->minLength(5),
            ])
            ->modalSubmitActionLabel('Tolak pembayaran')
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(RejectPayment::class)->handle($this->getRecord(), $data), 'Pembayaran ditolak'));
    }

    private function reverseAction(): Action
    {
        return Action::make('reverse')
            ->label('Balik pembayaran')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (): bool => $this->getRecord()->status->equals(Verified::class) && $this->can('reverse'))
            ->modalHeading('Balik pembayaran ini?')
            ->modalDescription('Pembayaran tetap tersimpan dengan status dibalik. Tagihan yang dilunasinya kembali belum dibayar, dan saldo kredit dari pembayaran ini ditarik lagi.')
            ->schema([
                Textarea::make('reason')->label('Alasan')->placeholder('Misal: transfer ditolak bank, atau tercatat dua kali')->required()->minLength(5),
            ])
            ->modalSubmitActionLabel('Balik pembayaran')
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(ReversePayment::class)->handle($this->getRecord(), $data), 'Pembayaran dibalik'));
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

    private function isPending(): bool
    {
        return $this->getRecord()->status->equals(Pending::class);
    }

    private function can(string $ability): bool
    {
        return User::current()->can($ability, $this->getRecord());
    }
}
