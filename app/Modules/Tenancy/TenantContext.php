<?php

namespace App\Modules\Tenancy;

use App\Modules\Tenancy\Exceptions\MissingTenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Closure;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\Context;

/**
 * The tenant whose data the current request, job, or command may touch.
 *
 * The tenant id is copied into Laravel's Context so queued jobs run under the
 * tenant that dispatched them (NFR-ISO-02).
 */
#[Scoped]
final class TenantContext
{
    public const CONTEXT_KEY = 'tenant_id';

    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;

        Context::addHidden(self::CONTEXT_KEY, $tenant->getKey());
    }

    public function forget(): void
    {
        $this->tenant = null;

        Context::forgetHidden(self::CONTEXT_KEY);
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * @throws MissingTenantContext
     */
    public function tenant(): Tenant
    {
        return $this->tenant ?? throw new MissingTenantContext;
    }

    /**
     * @throws MissingTenantContext
     */
    public function id(): string
    {
        return $this->tenant()->getKey();
    }

    public function currentId(): ?string
    {
        return $this->tenant?->getKey();
    }

    /**
     * Run the callback as the given tenant, then restore the previous one.
     *
     * @template TReturn
     *
     * @param  Closure(Tenant): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;

        $this->set($tenant);

        try {
            return $callback($tenant);
        } finally {
            $previous !== null ? $this->set($previous) : $this->forget();
        }
    }

    /**
     * Run the callback once per active (not frozen) tenant. For scheduled
     * commands that work across tenants.
     *
     * @param  Closure(Tenant): mixed  $callback
     */
    public function each(Closure $callback): void
    {
        foreach (Tenant::query()->active()->lazyById() as $tenant) {
            $this->run($tenant, $callback);
        }
    }
}
