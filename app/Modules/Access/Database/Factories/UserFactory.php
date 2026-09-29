<?php

namespace App\Modules\Access\Database\Factories;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => app(TenantContext::class)->currentId() ?? Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function withRole(Role $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            app(TenantContext::class)->run(
                $user->tenant()->firstOrFail(),
                fn () => $user->assignRole($role->value),
            );
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
