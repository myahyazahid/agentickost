<?php

namespace App\Modules\Access\Permissions;

use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Makes spatie/laravel-permission read its team id from the tenant context,
 * so roles always resolve for the current tenant.
 */
final class TenantTeamResolver implements PermissionsTeamResolver
{
    public function getPermissionsTeamId(): ?string
    {
        return app(TenantContext::class)->currentId();
    }

    /**
     * Switching teams directly would bypass the tenant context. Use
     * TenantContext::run() instead.
     */
    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        if ($id !== null && $id !== $this->getPermissionsTeamId()) {
            throw new LogicException('Gunakan TenantContext::run() untuk berpindah tenant.');
        }
    }
}
