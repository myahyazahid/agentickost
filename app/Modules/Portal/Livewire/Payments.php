<?php

namespace App\Modules\Portal\Livewire;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\States\Payment\Verified;
use App\Modules\Payment\Support\ReceiptDocument;
use App\Modules\Portal\Support\PortalQueries;
use Illuminate\Contracts\View\View;

/**
 * Payment history with the status of each transfer, and the receipt once
 * it is verified (FR-PRT-02).
 */
class Payments extends PortalPage
{
    public function render(): View
    {
        $receipts = app(ReceiptDocument::class);

        return $this->page('portal::livewire.payments', 'Riwayat pembayaran', [
            'payments' => PortalQueries::payments($this->access())
                ->with(['contract.room'])
                ->orderByDesc('paid_at')
                ->limit(60)
                ->get(),
            'receiptUrl' => fn (Payment $payment): ?string => $payment->status->equals(Verified::class) && ReceiptDocument::exists($payment)
                ? $receipts->shareUrl($payment)
                : null,
        ]);
    }
}
