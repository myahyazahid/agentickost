<?php

namespace App\Modules\Payment\Support;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\States\Payment\Reversed;
use App\Modules\Payment\States\Payment\Verified;
use App\Modules\Tenancy\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * The receipt of a verified payment as a PDF carrying the tenant's name and
 * colour (FR-PAY-06), and the link staff send to the payer. A reversed
 * payment's receipt stays available, marked as cancelled.
 */
final class ReceiptDocument
{
    public const SHARE_DAYS = 30;

    public function stream(Payment $payment, Tenant $tenant): Response
    {
        $payment->loadMissing(['property', 'payer', 'contract.room', 'bankAccount', 'receivedBy', 'allocations.invoice']);

        return Pdf::loadView('payment::pdf.receipt', [
            'payment' => $payment,
            'tenant' => $tenant,
            'allocations' => $payment->allocations->sortBy('id'),
            'credited' => (int) $payment->creditTransactions()->where('amount', '>', 0)->sum('amount'),
        ])->stream(self::filename($payment));
    }

    public function shareUrl(Payment $payment): string
    {
        return URL::temporarySignedRoute('payment.receipts.shared', now()->addDays(self::SHARE_DAYS), ['payment' => $payment->id]);
    }

    public static function exists(Payment $payment): bool
    {
        return $payment->receipt_number !== null && $payment->status->equals(Verified::class, Reversed::class);
    }

    public static function filename(Payment $payment): string
    {
        return 'kuitansi-'.str_replace('/', '-', (string) $payment->receipt_number).'.pdf';
    }
}
