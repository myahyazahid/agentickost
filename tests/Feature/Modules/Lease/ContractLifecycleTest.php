<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Lease\Actions\ActivateContract;
use App\Modules\Lease\Actions\CancelNotice;
use App\Modules\Lease\Actions\DeleteDraftContract;
use App\Modules\Lease\Actions\GiveNotice;
use App\Modules\Lease\Actions\RenewContract;
use App\Modules\Lease\Actions\TerminateContract;
use App\Modules\Lease\Enums\Gender;
use App\Modules\Lease\Enums\PayerRelation;
use App\Modules\Lease\Events\ContractTerminated;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Lease\States\Contract\Completed;
use App\Modules\Lease\States\Contract\Draft;
use App\Modules\Lease\States\Contract\Notice;
use App\Modules\Lease\States\Contract\Terminated;
use App\Modules\Property\Actions\SetRoomPrice;
use App\Modules\Property\Actions\UpdatePropertySettings;
use App\Modules\Property\Enums\GenderPolicy;
use App\Modules\Property\Models\Property;
use App\Modules\Property\States\Room\Available;
use App\Modules\Property\States\Room\Occupied;
use App\Modules\Property\States\Room\Vacating;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    $this->travelTo('2026-09-15 03:00:00');
    loginAs(staff(Role::Owner));
});

it('drafts a contract with the rent locked and the primary resident paying', function () {
    $contract = LeaseScenario::draft();

    $payer = $contract->payer()->firstOrFail();
    expect($contract->status)->toBeInstanceOf(Draft::class)
        ->and($contract->number)->toBeNull()
        ->and($contract->rent_amount)->toBe(1_200_000)
        ->and($contract->billing_anchor_day)->toBe(20)
        ->and($payer->relation)->toBe(PayerRelation::Self)
        ->and($payer->resident_id)->toBe($contract->primaryResident()?->id)
        ->and($contract->room()->firstOrFail()->status)->toBeInstanceOf(Available::class);
});

it('anchors billing on the fixed day when the property bills on a fixed date', function () {
    $room = LeaseScenario::room();
    app(UpdatePropertySettings::class)->handle($room->property()->firstOrFail(), [
        ...$room->property()->firstOrFail()->resolvedSettings()->attributesToArray(),
        'billing_mode' => 'fixed_date',
        'fixed_billing_day' => 1,
    ]);

    expect(LeaseScenario::draft($room)->billing_anchor_day)->toBe(1);
});

it('records a parent as a separate payer', function () {
    $contract = LeaseScenario::draft(overrides: [
        'payer' => 'other',
        'payer_name' => 'Budi Santoso',
        'payer_phone' => '0811 222 333',
        'payer_relation' => 'parent',
    ]);

    $payer = $contract->payer()->firstOrFail();
    expect($payer->name)->toBe('Budi Santoso')
        ->and($payer->phone)->toBe('+62811222333')
        ->and($payer->resident_id)->toBeNull();
});

it('refuses residents the room cannot take', function (Closure $input, string $message) {
    $room = LeaseScenario::room(capacity: 1);

    expect(fn () => LeaseScenario::draft($room, $input($room)))->toThrow(ValidationException::class, $message);
})->with([
    'more residents than the capacity' => [
        fn () => ['resident_ids' => Resident::factory()->count(2)->create()->pluck('id')->all()],
        'hanya untuk 1 orang',
    ],
    'a resident still living elsewhere' => [
        fn () => ['resident_ids' => [LeaseScenario::active()->primaryResident()?->id]],
        'masih punya kontrak berjalan',
    ],
]);

it('refuses a resident who does not match a men-only or women-only kost', function () {
    $room = LeaseScenario::room();
    Property::query()->findOrFail($room->property_id)->update(['gender_policy' => GenderPolicy::Female]);

    LeaseScenario::draft($room, ['resident_ids' => [Resident::factory()->create(['gender' => Gender::Male])->id]]);
})->throws(ValidationException::class, 'tidak sesuai dengan jenis kost Putri');

it('keeps contracts of unassigned properties from a manager', function () {
    $room = LeaseScenario::room();
    loginAs(staff(Role::Manager, $room->tenant()->firstOrFail()));

    LeaseScenario::draft($room);
})->throws(AuthorizationException::class);

it('numbers the contract and occupies the room on activation', function () {
    $contract = LeaseScenario::active();

    expect($contract->status)->toBeInstanceOf(Active::class)
        ->and($contract->number)->toBe('KTR/2026/0001')
        ->and($contract->room()->firstOrFail()->status)->toBeInstanceOf(Occupied::class)
        ->and(LeaseScenario::active()->number)->toBe('KTR/2026/0002');
});

it('refuses a second running contract on the same room', function () {
    $room = LeaseScenario::room(capacity: 2);
    LeaseScenario::active($room);

    LeaseScenario::active($room);
})->throws(ValidationException::class, 'masih dipakai kontrak lain');

it('keeps the locked rent when the room price changes', function () {
    $room = LeaseScenario::room();
    $contract = LeaseScenario::active($room);

    app(SetRoomPrice::class)->handle($room, ['rental_period' => 'monthly', 'amount' => 1_500_000, 'effective_from' => '2026-10-01']);

    expect($contract->fresh()?->rent_amount)->toBe(1_200_000);
});

it('marks the room as vacating on notice and back when the notice is cancelled', function () {
    $contract = LeaseScenario::active();

    app(GiveNotice::class)->handle($contract, ['planned_move_out_on' => '2026-10-31']);
    expect($contract->status)->toBeInstanceOf(Notice::class)
        ->and($contract->notice_given_on?->toDateString())->toBe('2026-09-15')
        ->and($contract->room()->firstOrFail()->status)->toBeInstanceOf(Vacating::class);

    app(CancelNotice::class)->handle($contract);
    expect($contract->status)->toBeInstanceOf(Active::class)
        ->and($contract->planned_move_out_on)->toBeNull()
        ->and($contract->room()->firstOrFail()->status)->toBeInstanceOf(Occupied::class);
});

it('applies the early-termination penalty when a fixed-term contract ends early', function () {
    Event::fake([ContractTerminated::class]);
    $contract = LeaseScenario::active(overrides: ['end_date' => '2027-09-19', 'early_termination_penalty_amount' => 600_000]);

    app(TerminateContract::class)->handle($contract, ['ended_on' => '2026-12-31', 'termination_reason' => 'Pindah kerja']);

    expect($contract->status)->toBeInstanceOf(Terminated::class)
        ->and($contract->termination_penalty_amount)->toBe(600_000)
        ->and($contract->room()->firstOrFail()->status)->toBeInstanceOf(Vacating::class);
    Event::assertDispatched(ContractTerminated::class);
});

it('charges no penalty when a contract ends on its end date', function () {
    $contract = LeaseScenario::active(overrides: ['end_date' => '2026-12-31', 'early_termination_penalty_amount' => 600_000]);

    app(TerminateContract::class)->handle($contract, ['ended_on' => '2026-12-31', 'termination_reason' => 'Selesai kuliah']);

    expect($contract->termination_penalty_amount)->toBeNull();
});

it('renews into a linked contract that takes over on its start date', function () {
    $contract = LeaseScenario::active(overrides: ['end_date' => '2026-12-19']);

    $renewal = app(RenewContract::class)->handle($contract, ['rent_amount' => 1_300_000, 'end_date' => '2027-12-19']);

    expect($renewal->start_date->toDateString())->toBe('2026-12-20')
        ->and($renewal->renewed_from_contract_id)->toBe($contract->id)
        ->and($renewal->primaryResident()?->id)->toBe($contract->primaryResident()?->id)
        ->and(fn () => app(ActivateContract::class)->handle($renewal))->toThrow(ValidationException::class, 'aktif otomatis');

    $this->travelTo('2026-12-20 03:00:00');
    $this->artisan('contracts:activate-renewals')->expectsOutputToContain('1 perpanjangan diaktifkan.')->assertSuccessful();

    expect($contract->fresh()?->status)->toBeInstanceOf(Completed::class)
        ->and($contract->fresh()?->ended_on?->toDateString())->toBe('2026-12-19')
        ->and($renewal->fresh()?->status)->toBeInstanceOf(Active::class)
        ->and($renewal->fresh()?->rent_amount)->toBe(1_300_000)
        ->and($renewal->room()->firstOrFail()->status)->toBeInstanceOf(Occupied::class);
});

it('gives an open-ended contract an end date when it is renewed', function () {
    $contract = LeaseScenario::active();

    app(RenewContract::class)->handle($contract, ['rent_amount' => 1_300_000, 'start_date' => '2027-01-20']);

    expect($contract->fresh()?->end_date?->toDateString())->toBe('2027-01-19');
});

it('deletes a draft but not a running contract', function () {
    $draft = LeaseScenario::draft();
    app(DeleteDraftContract::class)->handle($draft);
    expect(Contract::query()->find($draft->id))->toBeNull();

    app(DeleteDraftContract::class)->handle(LeaseScenario::active());
})->throws(ValidationException::class, 'Hanya kontrak draf yang bisa dihapus.');
