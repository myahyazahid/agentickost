<?php

namespace App\Modules\Lease\Http\Controllers;

use App\Modules\Lease\Models\Contract;
use App\Modules\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable contract carrying the tenant's name and colour (FR-KTR-06).
 */
final class ContractPdfController
{
    public function __invoke(Request $request, TenantContext $tenants, string $contract): Response
    {
        $record = Contract::query()
            ->with(['property', 'room.roomType', 'payer', 'holds'])
            ->whereKey($contract)
            ->firstOrFail();

        Gate::forUser($request->user())->authorize('view', $record);

        $filename = 'kontrak-'.str_replace('/', '-', $record->number ?? 'draf-'.$record->id).'.pdf';

        return Pdf::loadView('lease::pdf.contract', [
            'contract' => $record,
            'tenant' => $tenants->tenant(),
            'occupants' => $record->residents()->wherePivotNull('left_on')->orderByPivot('is_primary', 'desc')->get(),
        ])->stream($filename);
    }
}
