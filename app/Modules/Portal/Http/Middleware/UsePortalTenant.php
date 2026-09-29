<?php

namespace App\Modules\Portal\Http\Middleware;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the tenant from the slug in the portal URL, /p/{tenant}. One phone
 * number can live in two tenants, so the URL decides which one is meant
 * (schema §16 no. 3). A frozen tenant's portal is closed.
 */
final class UsePortalTenant
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');
        $tenant = is_string($slug) ? Tenant::query()->active()->where('slug', $slug)->first() : null;

        abort_if($tenant === null, 404);

        $this->tenants->set($tenant);
        URL::defaults(['tenant' => $tenant->slug]);
        View::share('portalTenant', $tenant);

        return $next($request);
    }
}
