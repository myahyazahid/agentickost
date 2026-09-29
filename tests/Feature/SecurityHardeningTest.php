<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Lease\Actions\CreateResident;
use App\Modules\Lease\Actions\UpdateResident;
use App\Modules\Lease\Models\Resident;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Models\Property;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\LeaseScenario;

/*
 * Hardening from the pre-pilot security audit (NFR-SEC-04).
 */

it('sends browser protections on pages and on errors', function (string $path, int $status) {
    $this->get($path)
        ->assertStatus($status)
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy')
        ->assertHeaderMissing('Strict-Transport-Security');
})->with([
    'login page' => ['/app/login', 200],
    'missing page' => ['/tidak-ada', 404],
]);

it('pins HTTPS only when the request came over HTTPS', function () {
    $this->get('https://localhost/app/login')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

/**
 * @param  array<string, mixed>  $overrides
 */
function residentWithIdentity(array $overrides = []): Resident
{
    return app(CreateResident::class)->handle([
        'full_name' => 'Rina Wulandari',
        'phone' => '0812 3456 7890',
        'identity_type' => 'ktp',
        'identity_number' => '3174012345670001',
        ...$overrides,
    ]);
}

it('lets only staff who may see identities change them', function () {
    $owner = loginAs(staff(Role::Owner));
    $resident = residentWithIdentity();
    $contract = LeaseScenario::active(overrides: ['resident_ids' => [$resident->id]]);
    $caretaker = staff(Role::Caretaker, $owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($contract->property()->firstOrFail(), $caretaker);
    loginAs($caretaker);

    $input = ['full_name' => 'Rina W.', 'phone' => '0812 3456 7890', 'identity_type' => 'ktp'];

    expect(app(UpdateResident::class)->handle($resident, $input)->full_name)->toBe('Rina W.')
        ->and(fn () => app(UpdateResident::class)->handle($resident, [...$input, 'identity_number' => '3174012345670002']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateResident::class)->handle($resident, [...$input, 'identity_type' => 'sim']))->toThrow(AuthorizationException::class);
});

it('names the resident behind a duplicate identity number only to staff who can see them', function () {
    $owner = loginAs(staff(Role::Owner));
    $resident = residentWithIdentity();
    LeaseScenario::active(overrides: ['resident_ids' => [$resident->id]]);

    expect(fn () => residentWithIdentity(['full_name' => 'Orang lain', 'phone' => '0813 1111 2222']))
        ->toThrow(ValidationException::class, 'atas nama Rina Wulandari');

    $manager = staff(Role::Manager, $owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle(Property::factory()->create(), $manager);
    loginAs($manager);

    try {
        residentWithIdentity(['full_name' => 'Orang lain', 'phone' => '0813 1111 2222']);
        $this->fail('Nomor identitas ganda seharusnya ditolak.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['identity_number'][0])->toBe('Nomor identitas ini sudah terdaftar.');
    }
});
