<?php

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\PlatformSettings;
use App\Support\Actions\Action;
use App\Support\Timezone;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Creates a tenant with a trial of the length the super admin set
 * (FR-TNT-03). Listeners of TenantCreated (such as role provisioning) run
 * inside the same transaction.
 */
final class CreateTenant extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): Tenant
    {
        $this->authorize('create', Tenant::class);

        $data = $this->validate($input, [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash:ascii', Rule::unique('tenants', 'slug')],
            'default_timezone' => ['nullable', Rule::enum(Timezone::class)],
        ]);

        return $this->transaction(fn (): Tenant => Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $this->uniqueSlug($data['name']),
            'default_timezone' => $data['default_timezone'] ?? Timezone::Wib->value,
            'trial_ends_at' => now()->addDays(PlatformSettings::trialDays()),
        ]));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 70, '') ?: 'tenant';
        $slug = $base;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
