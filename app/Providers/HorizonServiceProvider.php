<?php

namespace App\Providers;

use App\Modules\Tenancy\Models\PlatformAdmin;
use Illuminate\Http\Request;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Outside the local environment only super admins (the `platform` guard)
     * may open the dashboard.
     */
    protected function authorization(): void
    {
        Horizon::auth(fn (Request $request): bool => app()->environment('local')
            || $request->user('platform') instanceof PlatformAdmin);
    }
}
