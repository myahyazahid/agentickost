<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The tenant's logo for documents (FR-SUB-07). PDFs embed it as a data URI,
 * since the renderer cannot fetch from private storage.
 */
final class TenantBranding
{
    public const LOGO_DIRECTORY = 'branding';

    public const LOGO_MAX_KB = 1024;

    /**
     * @var list<string>
     */
    public const LOGO_TYPES = ['image/png', 'image/jpeg'];

    public static function logoDataUri(Tenant $tenant): ?string
    {
        if ($tenant->logo_path === null) {
            return null;
        }

        try {
            $contents = Storage::get($tenant->logo_path);
            $type = Storage::mimeType($tenant->logo_path);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (! is_string($contents) || ! in_array($type, self::LOGO_TYPES, true)) {
            return null;
        }

        return "data:{$type};base64,".base64_encode($contents);
    }
}
