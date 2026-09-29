<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Access\Models\User;
use App\Modules\Access\Support\Impersonation;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the tenant and the actor from the logged-in user. Runs after
 * authentication.
 *
 * During impersonation the actor is the super admin (FR-TNT-05). A session
 * that no longer holds, for example because the admin logged out of the
 * admin panel, drops the owner login instead of carrying on as the owner.
 */
final class SetTenantContext
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly ActorContext $actors,
        private readonly Impersonation $impersonation,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($this->impersonation->inProgress()) {
            $log = $this->impersonation->current();
            $admin = $this->impersonation->admin();

            if ($log === null || $admin === null) {
                $this->impersonation->leave();

                return redirect('/admin');
            }

            $this->tenants->set($user->tenant()->firstOrFail());
            $this->actors->set(Actor::impersonating($admin, $user, $log->id, $request->ip()));

            return $next($request);
        }

        $this->tenants->set($user->tenant()->firstOrFail());
        $this->actors->set(Actor::user($user, $request->ip()));

        return $next($request);
    }
}
