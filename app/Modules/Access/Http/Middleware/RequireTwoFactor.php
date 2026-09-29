<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Access\Support\Impersonation;
use App\Modules\Tenancy\Models\PlatformAdmin;
use Closure;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends owners and accountants (FR-USR-05) and super admins (NFR-SEC-05)
 * to set up an authenticator app before they can use their panel. Other
 * staff may set one up from their profile but are not made to.
 *
 * A super admin inside a tenant is already past their own second factor,
 * so the owner account they entered with is not asked for one.
 */
final class RequireTwoFactor
{
    /**
     * Roles that must use two-factor authentication.
     *
     * @var list<Role>
     */
    public const REQUIRED_ROLES = [Role::Owner, Role::Accountant];

    public function __construct(private readonly Impersonation $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();
        $setUpUrl = Filament::getSetUpRequiredMultiFactorAuthenticationUrl();

        if (! config('agentickost.two_factor_required') || $user === null || $setUpUrl === null || ! self::mustUse($user) || $this->impersonation->inProgress()) {
            return $next($request);
        }

        if (MultiFactorChallenge::make()->hasEnabledProviders($user)) {
            return $next($request);
        }

        return redirect()->guest($setUpUrl);
    }

    public static function mustUse(Authenticatable $user): bool
    {
        return $user instanceof PlatformAdmin
            || ($user instanceof User && $user->hasAnyRole(array_map(fn (Role $role): string => $role->value, self::REQUIRED_ROLES)));
    }
}
