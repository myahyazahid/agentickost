<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Lease\Actions\AcknowledgeInspection;
use App\Modules\Lease\Actions\MoveRoom;
use App\Modules\Lease\Actions\RecordCheckIn;
use App\Modules\Lease\Actions\RecordCheckOut;
use App\Modules\Lease\Actions\RenewContract;
use App\Modules\Lease\Enums\InspectionType;
use App\Modules\Lease\Enums\ItemCondition;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Actions\StartRoomMaintenance;
use App\Modules\Property\Models\Room;
use App\Modules\Tenancy\Support\TenantStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    $this->travelTo('2026-09-20 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->room = LeaseScenario::room();
});

/**
 * @return array<string, mixed>
 */
function checkInInput(array $overrides = []): array
{
    return [
        'inspected_on' => '2026-09-20',
        'items' => [
            ['item_name' => 'Kasur dan dipan', 'condition' => 'good'],
            ['item_name' => 'Lemari', 'condition' => 'fair', 'notes' => 'Engsel pintu kiri longgar'],
        ],
        ...$overrides,
    ];
}

it('checks in with a checklist, photos, and the resident agreeing, activating a draft contract', function () {
    Storage::fake();
    $photo = app(TenantStorage::class)->path(AttachmentCollection::Inspection->directory().'/kamar.jpg');
    Storage::put($photo, 'jpeg-bytes');
    $contract = LeaseScenario::draft($this->room);

    $inspection = app(RecordCheckIn::class)->handle($contract, checkInInput(['resident_acknowledged' => true, 'photos' => [$photo]]));

    expect($contract->refresh()->status)->toBeInstanceOf(Active::class)
        ->and($inspection->type)->toBe(InspectionType::CheckIn)
        ->and($inspection->resident_acknowledged_at)->not->toBeNull()
        ->and($inspection->items()->pluck('condition')->all())->toBe([ItemCondition::Good, ItemCondition::Fair])
        ->and($inspection->attachmentPaths(AttachmentCollection::Inspection))->toBe([$photo])
        ->and(fn () => app(RecordCheckIn::class)->handle($contract, checkInInput()))->toThrow(ValidationException::class, 'sudah check-in');
});

it('lets the caretaker check in a running contract and record the resident agreeing later', function () {
    $contract = LeaseScenario::active($this->room);
    $caretaker = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->room->property()->firstOrFail(), $caretaker);
    loginAs($caretaker);

    $inspection = app(RecordCheckIn::class)->handle($contract, checkInInput());

    expect($inspection->resident_acknowledged_at)->toBeNull();

    app(AcknowledgeInspection::class)->handle($inspection);

    expect($inspection->refresh()->resident_acknowledged_at)->not->toBeNull();
});

it('keeps activating a draft contract for staff who manage contracts', function () {
    $contract = LeaseScenario::draft($this->room);
    $caretaker = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->room->property()->firstOrFail(), $caretaker);
    loginAs($caretaker);

    app(RecordCheckIn::class)->handle($contract, checkInInput());
})->throws(AuthorizationException::class);

it('moves only to a free room of the same property', function () {
    $contract = LeaseScenario::active($this->room, ['start_date' => '2026-09-01']);
    $busy = Room::factory()->forType($this->room->roomType()->firstOrFail())->create(['capacity' => 1]);
    LeaseScenario::active($busy);
    $repairing = Room::factory()->forType($this->room->roomType()->firstOrFail())->create(['capacity' => 1]);
    app(StartRoomMaintenance::class)->handle($repairing);
    $elsewhere = LeaseScenario::room();

    $move = fn (Room $to, string $on = '2026-09-20') => app(MoveRoom::class)->handle($contract, ['to_room_id' => $to->id, 'moved_on' => $on, 'new_rent_amount' => 1_000_000]);

    expect(fn () => $move($busy))->toThrow(ValidationException::class, 'tidak tersedia')
        ->and(fn () => $move($repairing))->toThrow(ValidationException::class, 'tidak tersedia')
        ->and(fn () => $move($elsewhere))->toThrow(ValidationException::class, 'properti yang sama')
        ->and(fn () => $move($this->room))->toThrow(ValidationException::class, 'properti yang sama');
});

it('refuses a move dated before the contract started or with a renewal pending', function () {
    $contract = LeaseScenario::active($this->room);
    $free = Room::factory()->forType($this->room->roomType()->firstOrFail())->create(['capacity' => 1]);

    expect(fn () => app(MoveRoom::class)->handle($contract, ['to_room_id' => $free->id, 'moved_on' => '2026-09-20', 'new_rent_amount' => 1_000_000]))
        ->toThrow(ValidationException::class);

    $this->travelTo('2026-10-05 03:00:00');
    app(RenewContract::class)->handle($contract, ['start_date' => '2027-09-20', 'rent_amount' => 1_300_000]);

    app(MoveRoom::class)->handle($contract->refresh(), ['to_room_id' => $free->id, 'moved_on' => '2026-10-05', 'new_rent_amount' => 1_000_000]);
})->throws(ValidationException::class, 'perpanjangan');

it('asks for notice or termination before a running contract checks out', function () {
    $contract = LeaseScenario::active($this->room);

    app(RecordCheckOut::class)->handle($contract, [
        'moved_out_on' => '2026-09-20',
        'room_after' => 'available',
        'items' => [['item_name' => 'Lemari', 'condition' => 'good']],
    ]);
})->throws(ValidationException::class, 'rencana keluar');
