<?php

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\Actions\EndImpersonation;
use App\Modules\Access\Support\Impersonation;
use App\Modules\Tenancy\Filament\Admin\Resources\Tenants\TenantResource;
use Illuminate\Http\RedirectResponse;

/**
 * Ends a super admin's session inside a tenant and returns to that tenant's
 * page in the admin panel (FR-TNT-05).
 */
final class EndImpersonationController
{
    public function __invoke(Impersonation $impersonation, EndImpersonation $end): RedirectResponse
    {
        $log = $impersonation->current();

        if ($log !== null) {
            $end->handle($log);
        }

        $impersonation->leave();

        return $log !== null
            ? redirect(TenantResource::getUrl('view', ['record' => $log->tenant_id], panel: 'admin'))
            : redirect('/admin');
    }
}
