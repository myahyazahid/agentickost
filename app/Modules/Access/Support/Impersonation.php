<?php

namespace App\Modules\Access\Support;

use App\Modules\Access\Models\User;
use App\Modules\Tenancy\Models\ImpersonationLog;
use App\Modules\Tenancy\Models\PlatformAdmin;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;

/**
 * The browser side of an impersonation session (FR-TNT-05): the super admin
 * stays logged in on the `platform` guard and is logged in on the `web`
 * guard as the tenant's owner account. The session remembers which
 * impersonation log it belongs to.
 */
final class Impersonation
{
    public const SESSION_KEY = 'impersonation_log_id';

    public function enter(ImpersonationLog $log, User $owner): void
    {
        Auth::guard('web')->login($owner);
        session()->put(self::SESSION_KEY, $log->id);
    }

    public function inProgress(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    /**
     * The running session, or null when it no longer holds: it was ended,
     * the admin logged out of the admin panel, or the web login changed.
     */
    public function current(): ?ImpersonationLog
    {
        $id = session()->get(self::SESSION_KEY);
        $log = is_string($id) ? ImpersonationLog::query()->find($id) : null;

        if ($log === null || ! $log->isActive()) {
            return null;
        }

        $admin = $this->admin();
        $user = Auth::guard('web')->user();

        if ($admin?->id !== $log->platform_admin_id || ! $user instanceof User || $user->id !== $log->impersonated_user_id) {
            return null;
        }

        return $log;
    }

    public function admin(): ?PlatformAdmin
    {
        $admin = Auth::guard('platform')->user();

        return $admin instanceof PlatformAdmin ? $admin : null;
    }

    /**
     * Leaves the owner account without touching the owner's own sessions on
     * other devices.
     */
    public function leave(): void
    {
        $guard = Auth::guard('web');

        if ($guard instanceof SessionGuard) {
            $guard->logoutCurrentDevice();
        }

        session()->forget(self::SESSION_KEY);
    }
}
