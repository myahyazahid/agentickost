<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Portal\Support\PortalTheme;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Web app manifest, so the portal can be installed on a phone (FR-PRT-06).
 * Each tenant's portal installs as its own app, named after the business.
 */
final class ManifestController
{
    public function __invoke(TenantContext $tenants): JsonResponse
    {
        $tenant = $tenants->tenant();
        $home = route('portal.home', absolute: false);

        return response()->json([
            'name' => $tenant->name,
            'short_name' => Str::limit($tenant->name, 12, ''),
            'description' => "Tagihan, pembayaran, dan laporan kerusakan di {$tenant->name}.",
            'lang' => 'id',
            'start_url' => $home,
            'scope' => rtrim($home, '/').'/',
            'display' => 'standalone',
            'background_color' => '#faf7f7',
            'theme_color' => PortalTheme::accent($tenant),
            'icons' => [
                ['src' => route('portal.icon', ['size' => 192], absolute: false), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('portal.icon', ['size' => 512], absolute: false), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('portal.icon', ['size' => 512], absolute: false), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
