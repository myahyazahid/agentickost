<?php

namespace App\Modules\Portal\Livewire;

use App\Modules\Lease\Models\Payer;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\Support\PortalAccess;
use App\Modules\Portal\Support\PortalSession;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A page of the resident portal behind login. Every query goes through
 * PortalAccess, never through an id the browser sent alone.
 */
abstract class PortalPage extends Component
{
    protected function access(): PortalAccess
    {
        return app(PortalAccess::class);
    }

    protected function tenant(): Tenant
    {
        return app(TenantContext::class)->tenant();
    }

    protected function account(): Resident|Payer
    {
        return app(PortalSession::class)->account() ?? abort(403);
    }

    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    protected function page(string $view, string $title, array $data = []): View
    {
        $shared = ['portalTenant' => $this->tenant(), 'portalAccess' => $this->access(), 'portalAccount' => $this->account()];

        return view($view, [...$shared, ...$data])->layout('portal::layout', ['title' => $title, ...$shared]);
    }
}
