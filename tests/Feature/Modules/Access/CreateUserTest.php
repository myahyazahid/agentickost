<?php

use App\Modules\Access\Actions\CreateUser;
use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function staffInput(array $overrides = []): array
{
    return [
        'name' => 'Budi Penjaga',
        'email' => 'budi@example.com',
        'password' => 'password-rahasia',
        'role' => Role::Caretaker->value,
        ...$overrides,
    ];
}

it('lets an owner add staff to their tenant with a role', function () {
    $owner = loginAs(staff(Role::Owner));

    $user = app(CreateUser::class)->handle(staffInput());

    expect($user->tenant_id)->toBe($owner->tenant_id)
        ->and($user->hasRole(Role::Caretaker->value))->toBeTrue();
});

it('forbids staff without user.manage from adding users', function () {
    loginAs(staff(Role::Manager));

    app(CreateUser::class)->handle(staffInput());
})->throws(AuthorizationException::class);

it('forbids adding users when nobody is logged in', function () {
    tenancy()->set(staff(Role::Owner)->tenant()->firstOrFail());

    app(CreateUser::class)->handle(staffInput());
})->throws(AuthorizationException::class);

it('rejects an email that another tenant already uses', function () {
    User::factory()->create(['email' => 'budi@example.com']);
    loginAs(staff(Role::Owner));

    app(CreateUser::class)->handle(staffInput());
})->throws(ValidationException::class);

it('rejects the resident role for staff', function () {
    loginAs(staff(Role::Owner));

    app(CreateUser::class)->handle(staffInput(['role' => Role::Resident->value]));
})->throws(ValidationException::class);
