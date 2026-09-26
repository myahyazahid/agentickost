<?php

namespace App\Modules\Lease\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Enums\LeasePermission;
use App\Modules\Lease\Models\Contract;
use App\Modules\Property\Models\Property;

final class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(LeasePermission::ViewContracts->value);
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->can(LeasePermission::ViewContracts->value) && $contract->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return $user->can(LeasePermission::ManageContracts->value);
    }

    public function createIn(User $user, Property $property): bool
    {
        return $user->can(LeasePermission::ManageContracts->value) && $property->isAccessibleBy($user);
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->can(LeasePermission::ManageContracts->value) && $contract->isAccessibleBy($user);
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $this->update($user, $contract);
    }
}
