<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\TenantContext;
use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;

/**
 * Cache whose keys are prefixed with the current tenant (NFR-ISO-04).
 * Use it for every cached value that holds tenant data.
 */
final class TenantCache
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly Repository $cache,
    ) {}

    public function key(string $key): string
    {
        return 'tenant:'.$this->tenants->id().':'.$key;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->key($key), $default);
    }

    public function has(string $key): bool
    {
        return $this->cache->has($this->key($key));
    }

    public function put(string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null): bool
    {
        return $this->cache->put($this->key($key), $value, $ttl);
    }

    /**
     * @template TCacheValue
     *
     * @param  Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function remember(string $key, DateTimeInterface|DateInterval|int|null $ttl, Closure $callback): mixed
    {
        return $this->cache->remember($this->key($key), $ttl, $callback);
    }

    public function forget(string $key): bool
    {
        return $this->cache->forget($this->key($key));
    }
}
