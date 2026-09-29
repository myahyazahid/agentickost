<?php

namespace App\Modules\Portal\Http\Middleware;

use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\Support\PortalAccess;
use App\Modules\Portal\Support\PortalSession;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets only a logged-in resident or payer of this tenant through, and makes
 * them the actor of what follows. Someone whose last contract no longer
 * gives them anything to see is logged out.
 */
final class AuthenticatePortal
{
    public function __construct(
        private readonly PortalSession $session,
        private readonly ActorContext $actors,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $account = $this->session->account();

        if ($account === null) {
            return redirect()->guest(route('portal.login'));
        }

        $access = PortalAccess::for($account);

        if ($access->contractIds() === []) {
            $this->session->logout();

            return redirect()->route('portal.login')->with('portal.status', 'Akses portal untuk nomor ini sudah berakhir. Hubungi pengelola kost bila ini keliru.');
        }

        app()->instance(PortalAccess::class, $access);
        View::share('portalAccount', $account);
        View::share('portalAccess', $access);

        $this->actors->set($account instanceof Resident
            ? Actor::resident($account, $request->ip())
            : Actor::payer($account, $request->ip()));

        return $next($request);
    }
}
