<?php

namespace App\Modules\Property\Actions;

use App\Modules\Access\Models\User;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\PropertyUser;
use App\Modules\Tenancy\Exceptions\TenantMismatch;
use App\Support\Actions\Action;

final class AssignStaffToProperty extends Action
{
    public function handle(Property $property, User $staff): PropertyUser
    {
        $this->authorize('assignStaff', $property);

        if ($staff->tenant_id !== $property->tenant_id) {
            throw TenantMismatch::forModel($staff, $property->tenant_id);
        }

        return $this->transaction(fn (): PropertyUser => PropertyUser::query()->firstOrCreate([
            'property_id' => $property->id,
            'user_id' => $staff->id,
        ]));
    }
}
