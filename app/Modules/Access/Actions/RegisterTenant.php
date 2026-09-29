<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Tenancy\Actions\CreateTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\Phone;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Self-registration of a kost business (FR-TNT-01, FR-TNT-02): a new tenant
 * with its trial, and its owner account, which still has to confirm its
 * email address. The public registration page runs this as the system
 * actor; validation here is the only gate.
 */
final class RegisterTenant extends Action
{
    public function __construct(
        private readonly CreateTenant $createTenant,
        private readonly CreateUser $createUser,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  array<string, mixed>  $input  business_name, name, email, phone, password
     */
    public function handle(array $input): User
    {
        $this->authorize('create', Tenant::class);

        $data = $this->validate(self::normalized($input), self::rules());

        return $this->transaction(function () use ($data): User {
            $tenant = $this->createTenant->handle(['name' => $data['business_name']]);

            return $this->tenants->run($tenant, fn (): User => $this->createUser->handle([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => Role::Owner->value,
                'email_verified' => false,
            ]));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $phone = fn (string $attribute, mixed $value, Closure $fail) => Phone::isValid(is_string($value) ? $value : null)
            ? null
            : $fail('Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.');

        return [
            'business_name' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', $phone],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalized(array $input): array
    {
        $input['phone'] = Phone::normalize(is_string($input['phone'] ?? null) ? $input['phone'] : null);

        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }

        return $input;
    }
}
