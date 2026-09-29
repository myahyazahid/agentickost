<?php

namespace Tests\Support;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Payer;
use App\Modules\Lease\Models\Resident;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Support\Facades\URL;

/**
 * Builders for portal tests. Call inside a tenant context with an acting
 * user who may manage contracts (see loginAs()).
 */
final class PortalScenario
{
    /**
     * A running contract whose resident pays for themselves.
     */
    public static function residentContract(string $phone = '+6281200000001'): Contract
    {
        $resident = Resident::factory()->create(['phone' => $phone]);

        return LeaseScenario::active(overrides: ['resident_ids' => [$resident->id]]);
    }

    /**
     * A running contract paid by a parent who does not live there.
     */
    public static function parentPaidContract(string $parentPhone = '+6281300000002'): Contract
    {
        return LeaseScenario::active(overrides: [
            'payer' => 'other',
            'payer_name' => 'Bapak Wali',
            'payer_phone' => $parentPhone,
            'payer_relation' => 'parent',
        ]);
    }

    public static function resident(Contract $contract): Resident
    {
        return $contract->residents()->firstOrFail();
    }

    public static function payer(Contract $contract): Payer
    {
        return $contract->payer()->firstOrFail();
    }

    /**
     * Route defaults the portal middleware would set, for Livewire tests
     * that do not pass through it.
     */
    public static function useTenantUrls(): void
    {
        URL::defaults(['tenant' => app(TenantContext::class)->tenant()->slug]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function url(string $route, array $parameters = []): string
    {
        return route($route, ['tenant' => app(TenantContext::class)->tenant()->slug, ...$parameters]);
    }
}
