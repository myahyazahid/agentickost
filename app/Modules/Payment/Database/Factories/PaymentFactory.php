<?php

namespace App\Modules\Payment\Database\Factories;

use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Enums\PaymentChannel;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Models\Payment;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pending payments; verify them through the Payment actions in tests.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'contract_id' => fn (array $attributes) => Contract::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'property_id' => fn (array $attributes) => self::contract($attributes)->property_id,
            'payer_id' => fn (array $attributes) => self::contract($attributes)->payer_id,
            'method' => PaymentMethod::Transfer,
            'channel' => PaymentChannel::Manual,
            'amount' => 1_200_000,
            'paid_at' => '2026-09-20 10:00:00',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function contract(array $attributes): Contract
    {
        return Contract::query()->withoutGlobalScopes()->whereKey($attributes['contract_id'])->firstOrFail();
    }
}
