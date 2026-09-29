<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Support\PortalAccess;
use App\Modules\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * The contract PDF for a resident or payer of that contract (FR-PRT-02).
 */
final class ContractPdfController
{
    public function __invoke(TenantContext $tenants, PortalAccess $access, string $tenant, string $contract): Response
    {
        abort_unless($access->canSee($contract), 404);

        $record = Contract::query()
            ->with(['property', 'room.roomType', 'payer', 'holds'])
            ->whereKey($contract)
            ->firstOrFail();

        return Pdf::loadView('lease::pdf.contract', [
            'contract' => $record,
            'tenant' => $tenants->tenant(),
            'occupants' => $record->residents()->wherePivotNull('left_on')->orderByPivot('is_primary', 'desc')->get(),
        ])->stream('kontrak-'.str_replace('/', '-', $record->number ?? $record->id).'.pdf');
    }
}
