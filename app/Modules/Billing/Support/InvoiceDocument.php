<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Draft;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Tenancy\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * The invoice as a PDF carrying the tenant's name and colour, and the link
 * staff send to the payer (FR-BIL-08). Call inside the invoice's tenant.
 */
final class InvoiceDocument
{
    /**
     * How long a shared link keeps working.
     */
    public const SHARE_DAYS = 30;

    public function stream(Invoice $invoice, Tenant $tenant): Response
    {
        $invoice->loadMissing(['property', 'payer', 'contract.room', 'items', 'penalties', 'creditNotes']);

        $accounts = BankAccount::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('property_id')->orWhere('property_id', $invoice->property_id))
            ->orderByDesc('is_default')
            ->orderBy('provider_name')
            ->get();

        return Pdf::loadView('billing::pdf.invoice', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'accounts' => $accounts,
            'penalties' => $invoice->penalties->whereNull('waived_at'),
        ])->stream(self::filename($invoice));
    }

    public function shareUrl(Invoice $invoice): string
    {
        return URL::temporarySignedRoute(
            'billing.invoices.shared',
            now()->addDays(self::SHARE_DAYS),
            ['invoice' => $invoice->id],
        );
    }

    public static function canShare(Invoice $invoice): bool
    {
        return ! $invoice->status->equals(Draft::class);
    }

    public static function filename(Invoice $invoice): string
    {
        return 'tagihan-'.str_replace('/', '-', $invoice->number ?? 'draf-'.$invoice->id).'.pdf';
    }
}
