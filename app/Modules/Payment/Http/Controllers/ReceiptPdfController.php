<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Support\ReceiptDocument;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The receipt PDF for logged-in staff of the tenant.
 */
final class ReceiptPdfController
{
    public function __invoke(Request $request, TenantContext $tenants, ReceiptDocument $document, string $payment): Response
    {
        $record = Payment::query()->whereKey($payment)->firstOrFail();

        Gate::forUser($request->user())->authorize('view', $record);
        abort_unless(ReceiptDocument::exists($record), 404);

        return $document->stream($record, $tenants->tenant());
    }
}
