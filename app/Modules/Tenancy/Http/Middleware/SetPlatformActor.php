<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the logged-in super admin the actor of admin panel requests, so
 * Actions authorize and audit them as the admin.
 */
final class SetPlatformActor
{
    public function __construct(private readonly ActorContext $actors) {}

    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('platform')->user();

        if ($admin instanceof PlatformAdmin) {
            $this->actors->set(Actor::platformAdmin($admin, $request->ip()));
        }

        return $next($request);
    }
}
