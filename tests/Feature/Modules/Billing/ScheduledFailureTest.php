<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Finance\Models\FiscalPeriod;
use App\Modules\Finance\States\FiscalPeriod\Closed;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Validation\ValidationException;
use Tests\Support\LeaseScenario;

/*
 * A scheduled run keeps going when one tenant fails (NFR-OBS-01): the
 * failure is reported, the other tenants are still processed, and the
 * command exits with failure so the schedule monitor raises an alert.
 */
beforeEach(function () {
    $this->travelTo('2026-09-20 03:00:00');
});

/**
 * @return array{0: Tenant, 1: string} a tenant with a contract due for its first invoice
 */
function tenantWithDueContract(): array
{
    $owner = loginAs(staff(Role::Owner));
    $contract = LeaseScenario::active();
    tenancy()->forget();
    actors()->forget();

    return [$owner->tenant()->firstOrFail(), $contract->id];
}

it('bills the other tenants, reports the failure, and exits with failure', function () {
    [$broken, $brokenContract] = tenantWithDueContract();
    [$healthy, $healthyContract] = tenantWithDueContract();

    tenancy()->run($broken, fn () => FiscalPeriod::for(now())->status->transitionTo(Closed::class));

    $this->artisan('billing:issue-invoices')
        ->expectsOutputToContain('1 tagihan diterbitkan.')
        ->expectsOutputToContain('1 bagian gagal dan sudah dilaporkan')
        ->assertFailed();

    Exceptions::assertReported(fn (RuntimeException $exception): bool => str_contains($exception->getMessage(), 'sudah ditutup')
        && $exception->getPrevious() instanceof ValidationException);

    expect(tenancy()->run($healthy, fn () => Invoice::query()->where('contract_id', $healthyContract)->exists()))->toBeTrue()
        ->and(tenancy()->run($broken, fn () => Invoice::query()->where('contract_id', $brokenContract)->exists()))->toBeFalse();
});

it('exits cleanly when every tenant succeeds', function () {
    tenantWithDueContract();

    $this->artisan('billing:issue-invoices')->assertSuccessful();

    Exceptions::assertNothingReported();
});
