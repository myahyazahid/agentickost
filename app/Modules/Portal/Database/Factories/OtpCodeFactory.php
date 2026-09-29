<?php

namespace App\Modules\Portal\Database\Factories;

use App\Modules\Portal\Enums\OtpPurpose;
use App\Modules\Portal\Models\OtpCode;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OtpCode>
 */
class OtpCodeFactory extends Factory
{
    protected $model = OtpCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'phone' => '+6281'.fake()->numerify('#########'),
            'purpose' => OtpPurpose::PortalLogin,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
        ];
    }
}
