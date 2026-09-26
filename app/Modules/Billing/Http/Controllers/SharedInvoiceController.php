<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Support\InvoiceDocument;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * The invoice PDF behind a signed link sent to the payer, who has no login.
 * The signature proves the link came from the app; the invoice alone then
 * decides the tenant, and everything else is read inside that tenant.
 */
final class SharedInvoiceController
{
    public function __invoke(TenantContext $tenants, InvoiceDocument $document, string $invoice): Response
    {
        $record = Invoice::withoutGlobalScopes()->whereKey($invoice)->firstOrFail();
        $tenant = Tenant::query()->active()->whereKey($record->tenant_id)->firstOrFail();

        return $tenants->run($tenant, function (Tenant $tenant) use ($document, $invoice): Response {
            $record = Invoice::query()->whereKey($invoice)->firstOrFail();

            abort_unless(InvoiceDocument::canShare($record), 404);

            return $document->stream($record, $tenant);
        });
    }
}
