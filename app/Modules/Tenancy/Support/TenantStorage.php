<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Stores tenant files under tenants/{tenant_id}/ and hands them out only
 * through short-lived signed URLs (NFR-ISO-03).
 */
final class TenantStorage
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function path(string $path = ''): string
    {
        return rtrim('tenants/'.$this->tenants->id().'/'.ltrim($path, '/'), '/');
    }

    /**
     * Whether the path sits inside the current tenant's directory.
     */
    public function owns(string $path): bool
    {
        $segments = explode('/', str_replace('\\', '/', $path));

        return str_starts_with($path, $this->path().'/')
            && ! in_array('..', $segments, true)
            && ! in_array('.', $segments, true);
    }

    public function putFile(string $directory, UploadedFile $file): string
    {
        $path = Storage::putFile($this->path($directory), $file);

        if ($path === false) {
            throw new RuntimeException("Gagal menyimpan berkas ke [{$directory}].");
        }

        return $path;
    }

    public function temporaryUrl(string $path, int $minutes = 5): string
    {
        if (! $this->owns($path)) {
            throw new RuntimeException('Berkas tidak berada di direktori tenant saat ini.');
        }

        return Storage::temporaryUrl($path, now()->addMinutes($minutes));
    }
}
