<?php

namespace App\Modules\Tenancy\Events;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;

final class TenantCreated
{
    use Dispatchable;

    public function __construct(public readonly Tenant $tenant) {}
}
