<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Lease\Actions\CreateResident;
use App\Modules\Lease\Actions\RevealResidentIdentity;
use App\Modules\Lease\Actions\UpdateResident;
use App\Modules\Lease\Models\Resident;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Models\Property;
use App\Support\Actors\ActorType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\LeaseScenario;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function residentInput(array $overrides = []): array
{
    return [
        'full_name' => 'Rina Kartika',
        'phone' => '0812-3456-7890',
        'identity_type' => 'ktp',
        'identity_number' => '3174 0123 4567 0001',
        ...$overrides,
    ];
}

it('stores the phone in E.164 and the identity number encrypted', function () {
    loginAs(staff(Role::Owner));

    $resident = app(CreateResident::class)->handle(residentInput());

    $raw = DB::table('residents')->where('id', $resident->id)->first();
    expect($resident->phone)->toBe('+6281234567890')
        ->and($resident->identity_number)->toBe('3174 0123 4567 0001')
        ->and($raw->identity_number)->not->toContain('3174')
        ->and($raw->identity_number_hash)->toHaveLength(64);
});

it('recognises an identity number already on file, whatever the formatting', function () {
    loginAs(staff(Role::Owner));
    app(CreateResident::class)->handle(residentInput());

    app(CreateResident::class)->handle(residentInput(['full_name' => 'Rina K.', 'identity_number' => '3174012345670001']));
})->throws(ValidationException::class, 'Nomor identitas ini sudah terdaftar atas nama Rina Kartika.');

it('keeps the stored identity number when the field is left empty', function () {
    loginAs(staff(Role::Owner));
    $resident = app(CreateResident::class)->handle(residentInput());

    app(UpdateResident::class)->handle($resident, residentInput(['identity_number' => '', 'institution' => 'ITB']));

    expect($resident->fresh()?->identity_number)->toBe('3174 0123 4567 0001')
        ->and($resident->fresh()?->institution)->toBe('ITB');
});

it('never writes the identity number into the audit log', function () {
    loginAs(staff(Role::Owner));

    $resident = app(CreateResident::class)->handle(residentInput());

    $entry = AuditLog::query()->where('subject_id', $resident->id)->sole();
    expect($entry->new_values['identity_number'])->toBe('[redacted]')
        ->and($entry->new_values['identity_number_hash'])->toBe('[redacted]');
});

it('leaves the "not recommended" flag to owners and managers', function () {
    $owner = staff(Role::Owner);
    $tenant = $owner->tenant()->firstOrFail();
    loginAs($owner);
    $resident = app(CreateResident::class)->handle(residentInput());

    loginAs(staff(Role::Accountant, $tenant));
    expect(fn () => app(UpdateResident::class)->handle($resident, residentInput(['identity_number' => '', 'is_flagged' => true])))
        ->toThrow(AuthorizationException::class);
});

it('shows the identity to an owner and records the look in the audit log', function () {
    Storage::fake();
    $owner = loginAs(staff(Role::Owner));
    $resident = app(CreateResident::class)->handle(residentInput());
    app(AttachmentSync::class)->store($resident, AttachmentCollection::Identity, UploadedFile::fake()->createWithContent('ktp.jpg', 'foto-ktp'));

    $identity = app(RevealResidentIdentity::class)->handle($resident);

    $entry = AuditLog::query()->where('event', 'resident.identity_viewed')->sole();
    expect($identity['identity_number'])->toBe('3174 0123 4567 0001')
        ->and($identity['documents'])->toHaveCount(1)
        ->and($entry->actor_type)->toBe(ActorType::User)
        ->and($entry->actor_id)->toBe($owner->id);

    $this->get($identity['documents'][0]['url'])->assertOk()->assertContent('foto-ktp');
});

it('refuses an identity document link without a valid signature', function () {
    Storage::fake();
    $owner = loginAs(staff(Role::Owner));
    $resident = app(CreateResident::class)->handle(residentInput());
    $document = app(AttachmentSync::class)->store($resident, AttachmentCollection::Identity, UploadedFile::fake()->createWithContent('ktp.jpg', 'foto-ktp'));

    $this->actingAs($owner)->get(route('attachments.show', ['attachment' => $document->id]))->assertForbidden();
});

it('keeps identities from caretakers', function () {
    $owner = staff(Role::Owner);
    loginAs($owner);
    $resident = app(CreateResident::class)->handle(residentInput());

    loginAs(staff(Role::Caretaker, $owner->tenant()->firstOrFail()));
    app(RevealResidentIdentity::class)->handle($resident);
})->throws(AuthorizationException::class);

it('shows a caretaker the residents of their properties and those without a contract', function () {
    $owner = staff(Role::Owner);
    $tenant = $owner->tenant()->firstOrFail();
    $caretaker = staff(Role::Caretaker, $tenant);
    loginAs($owner);
    $mine = LeaseScenario::active();
    $other = LeaseScenario::active();
    $newcomer = Resident::factory()->create();
    app(AssignStaffToProperty::class)->handle(Property::query()->findOrFail($mine->property_id), $caretaker);

    $visible = Resident::query()->accessibleBy($caretaker)->pluck('id')->all();

    expect($visible)->toContain($mine->primaryResident()?->id, $newcomer->id)
        ->not->toContain($other->primaryResident()?->id);
});
