<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Support\ReceiptDocument;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * The receipt PDF behind a signed link sent to the payer, who has no login.
 * The signature proves the link came from the app; the payment alone then
 * decides the tenant, and everything else is read inside that tenant.
 */
final class SharedReceiptController
{
    public function __invoke(TenantContext $tenants, ReceiptDocument $document, string $payment): Response
    {
        $record = Payment::withoutGlobalScopes()->whereKey($payment)->firstOrFail();
        $tenant = Tenant::query()->active()->whereKey($record->tenant_id)->firstOrFail();

        return $tenants->run($tenant, function (Tenant $tenant) use ($document, $payment): Response {
            $record = Payment::query()->whereKey($payment)->firstOrFail();

            abort_unless(ReceiptDocument::exists($record), 404);

            return $document->stream($record, $tenant);
        });
    }
}
