<?php

namespace Tests\Fixtures;

use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\ActorContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Records the tenant and actor it runs under.
 */
class RecordContextJob implements ShouldQueue
{
    use Queueable;

    public static ?string $tenantId = null;

    public static ?string $actorType = null;

    public static ?string $actorId = null;

    public function handle(TenantContext $tenants, ActorContext $actors): void
    {
        self::$tenantId = $tenants->currentId();
        self::$actorType = $actors->current()->type->value;
        self::$actorId = $actors->current()->id;
    }
}
