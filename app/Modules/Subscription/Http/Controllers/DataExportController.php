<?php

namespace App\Modules\Subscription\Http\Controllers;

use App\Modules\Subscription\Support\CurrentSubscription;
use App\Modules\Tenancy\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hands out a finished data export (FR-SUB-06). The signed link alone is
 * not enough: the viewer must still be allowed to export the tenant's data.
 */
final class DataExportController
{
    public function __invoke(Request $request, TenantStorage $storage, string $file): StreamedResponse
    {
        Gate::forUser($request->user())->authorize('exportData', CurrentSubscription::get());

        $path = $storage->path('exports/'.basename($file));

        abort_unless($storage->owns($path) && Storage::exists($path), 404, 'Arsip ekspor sudah tidak tersedia. Minta ekspor baru dari menu Langganan.');

        return Storage::download($path, basename($path), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
