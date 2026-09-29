<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Support\Actions\Action;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Adds a staff member with one role to the current tenant. Accounts made by
 * an owner, an invitation, or an operator count as verified; only
 * self-registration asks the owner to confirm the email address first.
 */
final class CreateUser extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): User
    {
        $this->authorize('create', User::class);

        $data = $this->validate($input, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'password' => ['required', 'string', Password::default()],
            'role' => ['required', Rule::enum(Role::class)->only(Role::staff())],
            'email_verified' => ['sometimes', 'boolean'],
        ]);

        return $this->transaction(function () use ($data): User {
            $user = new User(Arr::except($data, ['role', 'email_verified']));
            $user->email_verified_at = ($data['email_verified'] ?? true) ? now() : null;
            $user->save();

            $user->assignRole($data['role']);

            return $user;
        });
    }
}
