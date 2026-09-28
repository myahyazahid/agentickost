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

    /**
     * Record check-in and check-out inspections (FR-SIK-01, FR-SIK-04).
     */
    public function inspect(User $user, Contract $contract): bool
    {
        return $user->can(LeasePermission::InspectRooms->value) && $contract->isAccessibleBy($user);
    }

    /**
     * Settle a check-out: bill, use deposit and credit, pay back the rest
     * (FR-SIK-05).
     */
    public function finalizeSettlement(User $user, Contract $contract): bool
    {
        return $user->can(LeasePermission::FinalizeSettlements->value) && $contract->isAccessibleBy($user);
    }
}
