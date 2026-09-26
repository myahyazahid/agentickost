<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Access\Models\User;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the tenant and the actor from the logged-in user. Runs after
 * authentication.
 */
final class SetTenantContext
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly ActorContext $actors,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->tenants->set($user->tenant()->firstOrFail());
            $this->actors->set(Actor::user($user, $request->ip()));
        }

        return $next($request);
    }
}
