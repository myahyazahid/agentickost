<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The business logo in the portal header (FR-SUB-07), served from the
 * tenant's private storage.
 */
final class LogoController
{
    public function __invoke(TenantContext $tenants): Response
    {
        $path = $tenants->tenant()->logo_path;

        abort_if($path === null || ! Storage::exists($path), 404);

        return Storage::response($path, null, [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
