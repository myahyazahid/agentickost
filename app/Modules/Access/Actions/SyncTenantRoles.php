<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\PermissionRegistry;
use App\Support\Actions\Action;
use App\Support\Subscriptions\AllowedWhenReadOnly;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Creates the registered permissions and the staff roles of the current
 * tenant, and grants each role its default permissions. Safe to run again.
 *
 * Internal provisioning: called when a tenant is created and by
 * `access:sync-roles` after a deploy, not by users, so it does not authorize.
 */
final class SyncTenantRoles extends Action implements AllowedWhenReadOnly
{
    public function __construct(private readonly PermissionRegistry $permissions) {}

    public function handle(): void
    {
        $this->transaction(function (): void {
            foreach ($this->permissions->names() as $name) {
                Permission::findOrCreate($name, 'web');
            }

            foreach (Role::staff() as $role) {
                RoleModel::findOrCreate($role->value, 'web')
                    ->syncPermissions($this->permissions->namesFor($role));
            }
        });
    }
}
