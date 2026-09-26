<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\AuditLog;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @return list<AuditLog>
 */
function auditTrailOf(Property $property): array
{
    return AuditLog::query()
        ->where('subject_type', 'property')
        ->where('subject_id', $property->id)
        ->oldest('id')
        ->get()
        ->all();
}

it('records a created event with the new values', function () {
    tenancy()->set(Tenant::factory()->create());

    $property = Property::factory()->create(['name' => 'Kost Melati']);

    [$entry] = auditTrailOf($property);
    expect($entry->event)->toBe('property.created')
        ->and($entry->new_values['name'])->toBe('Kost Melati')
        ->and($entry->old_values)->toBeNull();
});

it('records only the changed attributes on update', function () {
    tenancy()->set(Tenant::factory()->create());
    $property = Property::factory()->create(['name' => 'Kost Melati', 'city' => 'Bandung']);

    $property->update(['name' => 'Kost Mawar']);

    $entry = auditTrailOf($property)[1];
    expect($entry->event)->toBe('property.updated')
        ->and($entry->old_values)->toBe(['name' => 'Kost Melati'])
        ->and($entry->new_values)->toBe(['name' => 'Kost Mawar']);
});

it('records deletes and restores', function () {
    tenancy()->set(Tenant::factory()->create());
    $property = Property::factory()->create();

    $property->delete();
    $property->restore();

    expect(array_map(fn (AuditLog $entry) => $entry->event, auditTrailOf($property)))
        ->toBe(['property.created', 'property.deleted', 'property.restored']);
});

it('redacts hidden attributes', function () {
    $owner = staff(Role::Owner);
    tenancy()->set($owner->tenant()->firstOrFail());

    $owner->update(['password' => 'rahasia-baru']);

    $entry = AuditLog::query()->where('event', 'user.updated')->sole();
    expect($entry->new_values)->toBe(['password' => '[redacted]']);
});

it('records the acting user and their ip address', function () {
    $owner = staff(Role::Owner);
    tenancy()->set($owner->tenant()->firstOrFail());
    actors()->set(Actor::user($owner, '203.0.113.7'));

    $property = Property::factory()->create();

    [$entry] = auditTrailOf($property);
    expect($entry->actor_type)->toBe(ActorType::User)
        ->and($entry->actor_id)->toBe($owner->id)
        ->and($entry->ip_address)->toBe('203.0.113.7');
});

it('records an agent as the actor', function () {
    tenancy()->set(Tenant::factory()->create());
    $agentRunId = (string) Str::ulid();

    $property = actors()->actingAs(Actor::agent($agentRunId), fn () => Property::factory()->create());

    [$entry] = auditTrailOf($property);
    expect($entry->actor_type)->toBe(ActorType::Agent)
        ->and($entry->actor_id)->toBe($agentRunId);
});

it('falls back to the system actor when nobody is acting', function () {
    tenancy()->set(Tenant::factory()->create());

    $property = Property::factory()->create();

    expect(auditTrailOf($property)[0]->actor_type)->toBe(ActorType::System);
});

it('rolls the audit entry back together with the change', function () {
    tenancy()->set(Tenant::factory()->create());

    try {
        DB::transaction(function () {
            Property::factory()->create();

            throw new RuntimeException('gagal di tengah transaksi');
        });
    } catch (RuntimeException) {
        // Expected: the transaction is rolled back.
    }

    expect(Property::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('subject_type', 'property')->count())->toBe(0);
});

it('refuses to change an audit entry', function (Closure $change) {
    tenancy()->set(Tenant::factory()->create());
    $entry = AuditLog::factory()->create();

    $change($entry);
})->with([
    'update' => fn (AuditLog $entry) => $entry->update(['event' => 'tampered']),
    'delete' => fn (AuditLog $entry) => $entry->delete(),
])->throws(LogicException::class);
