<?php

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\TenantBranding;
use App\Modules\Tenancy\Support\TenantStorage;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The owner's business name, logo, and accent colour, shown on invoices,
 * receipts, and contracts (FR-SUB-07). A replaced logo is deleted once the
 * change commits.
 */
final class UpdateTenantProfile extends Action
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly TenantStorage $storage,
    ) {}

    /**
     * @param  array<string, mixed>  $input  name, brand_color, logo_path
     */
    public function handle(array $input): Tenant
    {
        $tenant = $this->tenants->tenant();
        $this->authorize('updateProfile', $tenant);

        $data = $this->validate($input, [
            'name' => ['required', 'string', 'max:150'],
            'brand_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_path' => ['nullable', 'string', 'max:255'],
        ]);

        $logo = $data['logo_path'] ?? null;

        if ($logo !== null && $logo !== $tenant->logo_path) {
            $this->ensureFreshLogo($logo);
        }

        return $this->transaction(function () use ($tenant, $data, $logo): Tenant {
            $tenant = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $previousLogo = $tenant->logo_path;

            $tenant->name = $data['name'];
            $tenant->brand_color = isset($data['brand_color']) ? mb_strtolower($data['brand_color']) : null;
            $tenant->logo_path = $logo;
            $tenant->save();

            if ($previousLogo !== null && $previousLogo !== $logo) {
                DB::afterCommit(fn () => Storage::delete($previousLogo));
            }

            $this->tenants->set($tenant);

            return $tenant;
        });
    }

    /**
     * The path comes from form state, which the browser controls: it must be
     * a fresh upload in this tenant's logo folder, and an image the PDF
     * renderer can draw.
     */
    private function ensureFreshLogo(string $path): void
    {
        $valid = $this->storage->owns($path)
            && str_starts_with($path, $this->storage->path(TenantBranding::LOGO_DIRECTORY).'/')
            && Storage::exists($path)
            && in_array(Storage::mimeType($path), TenantBranding::LOGO_TYPES, true)
            && Storage::size($path) <= TenantBranding::LOGO_MAX_KB * 1024;

        if (! $valid) {
            throw ValidationException::withMessages([
                'logo_path' => 'Logo harus gambar PNG atau JPG paling besar '.TenantBranding::LOGO_MAX_KB.' KB. Unggah ulang logonya.',
            ]);
        }
    }
}
