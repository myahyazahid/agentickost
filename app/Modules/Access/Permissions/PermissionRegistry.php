<?php

namespace App\Modules\Access\Permissions;

use App\Modules\Access\Enums\Role;
use BackedEnum;
use Illuminate\Container\Attributes\Singleton;

/**
 * Collects the permission enums that modules register from their providers.
 */
#[Singleton]
final class PermissionRegistry
{
    /**
     * @var list<class-string<DefinesPermissions&BackedEnum>>
     */
    private array $enums = [];

    /**
     * @param  class-string<DefinesPermissions&BackedEnum>  $enum
     */
    public function register(string $enum): void
    {
        if (! in_array($enum, $this->enums, true)) {
            $this->enums[] = $enum;
        }
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(fn (BackedEnum $permission): string => (string) $permission->value, $this->all());
    }

    /**
     * @return list<string>
     */
    public function namesFor(Role $role): array
    {
        $names = [];

        foreach ($this->all() as $permission) {
            if (in_array($role, $permission->defaultRoles(), true)) {
                $names[] = (string) $permission->value;
            }
        }

        return $names;
    }

    /**
     * @return list<DefinesPermissions&BackedEnum>
     */
    private function all(): array
    {
        return array_merge(...array_map(fn (string $enum): array => $enum::cases(), $this->enums));
    }
}
