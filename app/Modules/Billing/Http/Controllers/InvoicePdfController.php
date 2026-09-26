<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Support\InvoiceDocument;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The invoice PDF for logged-in staff of the tenant.
 */
final class InvoicePdfController
{
    public function __invoke(Request $request, TenantContext $tenants, InvoiceDocument $document, string $invoice): Response
    {
        $record = Invoice::query()->whereKey($invoice)->firstOrFail();

        Gate::forUser($request->user())->authorize('view', $record);

        return $document->stream($record, $tenants->tenant());
    }
}
