<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Audit\AuditLogger;
use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Tenancy\Models\ImpersonationLog;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use Illuminate\Validation\ValidationException;

/**
 * Opens a support session inside a tenant (FR-TNT-05). A written reason is
 * required; the session is logged, written into the tenant's audit log, and
 * listed for the owner. The admin works as the tenant's first active owner.
 */
final class StartImpersonation extends Action
{
    public function __construct(
        private readonly ActorContext $actors,
        private readonly TenantContext $tenants,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: ImpersonationLog, 1: User}
     */
    public function handle(Tenant $tenant, array $input): array
    {
        $this->authorize('impersonate', $tenant);

        $data = $this->validate($input, [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        if ($tenant->isFrozen()) {
            throw ValidationException::withMessages(['reason' => "{$tenant->name} sedang dibekukan. Aktifkan dulu bila perlu masuk."]);
        }

        return $this->tenants->run($tenant, function () use ($tenant, $data): array {
            $owner = User::query()
                ->role(Role::Owner->value)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->first() ?? throw ValidationException::withMessages(['reason' => "{$tenant->name} belum punya owner yang aktif."]);

            $actor = $this->actors->current();

            return $this->transaction(function () use ($tenant, $owner, $actor, $data): array {
                $log = ImpersonationLog::create([
                    'platform_admin_id' => $actor->id,
                    'tenant_id' => $tenant->id,
                    'impersonated_user_id' => $owner->id,
                    'reason' => $data['reason'],
                    'started_at' => now(),
                    'ip_address' => $actor->ipAddress ?? request()->ip() ?? '',
                ]);

                $this->audit->record('impersonation.started', $log, null, ['impersonated_user_id' => $owner->id], $data['reason']);

                return [$log, $owner];
            });
        });
    }
}
