<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Support\Actions\Action;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Adds a staff member with one role to the current tenant.
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
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(Role::class)->only(Role::staff())],
        ]);

        return $this->transaction(function () use ($data): User {
            $user = User::create(Arr::except($data, 'role'));

            $user->assignRole($data['role']);

            return $user;
        });
    }
}
