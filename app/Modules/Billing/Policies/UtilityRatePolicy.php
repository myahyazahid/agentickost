<?php

namespace App\Modules\Billing\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Enums\BillingPermission;
use App\Modules\Billing\Models\UtilityRate;
use App\Modules\Property\Models\Property;

final class UtilityRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(BillingPermission::ViewInvoices->value);
    }

    public function view(User $user, UtilityRate $rate): bool
    {
        return $user->can(BillingPermission::ViewInvoices->value) && $rate->isAccessibleBy($user);
    }

    public function createIn(User $user, Property $property): bool
    {
        return $user->can(BillingPermission::ManageUtilityRates->value) && $property->isAccessibleBy($user);
    }

    public function update(User $user, UtilityRate $rate): bool
    {
        return $user->can(BillingPermission::ManageUtilityRates->value) && $rate->isAccessibleBy($user);
    }
}
