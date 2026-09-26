<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Lease\Actions\AddContractHold;
use App\Modules\Lease\Actions\AddResidentToContract;
use App\Modules\Lease\Actions\RemoveContractHold;
use App\Modules\Lease\Actions\RemoveResidentFromContract;
use App\Modules\Lease\Models\Resident;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Validation\ValidationException;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    $this->travelTo('2026-09-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
});

it('adds a second resident to a shared room and hands over the primary role when the first leaves', function () {
    $contract = LeaseScenario::active(LeaseScenario::room(capacity: 2));
    $first = $contract->primaryResident();
    $second = Resident::factory()->create();

    app(AddResidentToContract::class)->handle($contract, ['resident_id' => $second->id, 'joined_on' => '2026-10-01']);
    app(RemoveResidentFromContract::class)->handle($contract, $first ?? throw new LogicException, ['left_on' => '2026-11-30']);

    expect($contract->primaryResident()?->id)->toBe($second->id);
});

it('refuses a resident beyond the room capacity', function () {
    $contract = LeaseScenario::active(LeaseScenario::room(capacity: 1));

    app(AddResidentToContract::class)->handle($contract, ['resident_id' => Resident::factory()->create()->id, 'joined_on' => '2026-10-01']);
})->throws(ValidationException::class, 'hanya untuk 1 orang');

it('keeps the last resident on the contract', function () {
    $contract = LeaseScenario::active();

    app(RemoveResidentFromContract::class)->handle($contract, $contract->primaryResident() ?? throw new LogicException, ['left_on' => '2026-11-30']);
})->throws(ValidationException::class, 'Penghuni terakhir tidak bisa dikeluarkan.');

it('adds a holiday rate and refuses one that overlaps or outlasts the contract', function () {
    $contract = LeaseScenario::active(overrides: ['end_date' => '2027-06-30']);

    $hold = app(AddContractHold::class)->handle($contract, ['start_date' => '2027-01-01', 'end_date' => '2027-02-28', 'rent_amount' => 400_000]);

    expect($hold->rent_amount)->toBe(400_000)
        ->and(fn () => app(AddContractHold::class)->handle($contract, ['start_date' => '2027-02-01', 'end_date' => '2027-03-31', 'rent_amount' => 400_000]))
        ->toThrow(ValidationException::class, 'bertumpuk')
        ->and(fn () => app(AddContractHold::class)->handle($contract, ['start_date' => '2027-06-01', 'end_date' => '2027-07-31', 'rent_amount' => 400_000]))
        ->toThrow(ValidationException::class);
});

it('removes a hold only before it starts', function () {
    $contract = LeaseScenario::active();
    $future = app(AddContractHold::class)->handle($contract, ['start_date' => '2027-01-01', 'end_date' => '2027-01-31', 'rent_amount' => 400_000]);
    $started = app(AddContractHold::class)->handle($contract, ['start_date' => '2026-09-20', 'end_date' => '2026-10-19', 'rent_amount' => 400_000]);
    $this->travelTo('2026-09-25 03:00:00');

    app(RemoveContractHold::class)->handle($future);

    expect($contract->holds()->pluck('id')->all())->toBe([$started->id])
        ->and(fn () => app(RemoveContractHold::class)->handle($started))->toThrow(ValidationException::class, 'sudah dimulai');
});

it('reminds owners once when a contract nears its end', function () {
    LeaseScenario::active(overrides: ['end_date' => '2026-10-10']);
    LeaseScenario::active(overrides: ['end_date' => '2027-06-30']);

    $this->artisan('contracts:remind-ending')->expectsOutputToContain('1 kontrak diingatkan.')->assertSuccessful();
    $this->artisan('contracts:remind-ending')->expectsOutputToContain('0 kontrak diingatkan.')->assertSuccessful();

    expect($this->owner->notifications()->count())->toBe(1);
});

it('prints the contract as a PDF for staff of the tenant only', function () {
    $contract = LeaseScenario::active();

    $this->get(route('lease.contracts.pdf', ['contract' => $contract->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    tenancy()->forget();
    $outsider = staff(Role::Owner, Tenant::factory()->create());

    $this->actingAs($outsider)
        ->get(route('lease.contracts.pdf', ['contract' => $contract->id]))
        ->assertNotFound();
});
