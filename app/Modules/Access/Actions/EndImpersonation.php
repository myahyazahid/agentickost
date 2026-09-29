<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Audit\AuditLogger;
use App\Modules\Tenancy\Models\ImpersonationLog;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use App\Support\Subscriptions\AllowedWhenReadOnly;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Closes a support session (FR-TNT-05). Only the super admin who opened it
 * can close it. Gate policies cannot check this: during the session they
 * see the owner account, so the actor is compared directly.
 */
final class EndImpersonation extends Action implements AllowedWhenReadOnly
{
    public function __construct(
        private readonly ActorContext $actors,
        private readonly TenantContext $tenants,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(ImpersonationLog $log): ImpersonationLog
    {
        $actor = $this->actors->current();

        if ($actor->type !== ActorType::PlatformAdmin || $actor->id !== $log->platform_admin_id) {
            throw new AuthorizationException('Sesi ini hanya bisa diakhiri oleh super admin yang membukanya.');
        }

        if (! $log->isActive()) {
            return $log;
        }

        return $this->tenants->run($log->tenant()->firstOrFail(), fn (): ImpersonationLog => $this->transaction(function () use ($log): ImpersonationLog {
            $log->ended_at = now();
            $log->save();

            $this->audit->record('impersonation.ended', $log);

            return $log;
        }));
    }
}
