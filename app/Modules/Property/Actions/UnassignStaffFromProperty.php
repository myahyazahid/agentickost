<?php

namespace App\Modules\Property\Actions;

use App\Modules\Access\Models\User;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\PropertyUser;
use App\Support\Actions\Action;

final class UnassignStaffFromProperty extends Action
{
    public function handle(Property $property, User $staff): void
    {
        $this->authorize('assignStaff', $property);

        $this->transaction(function () use ($property, $staff): void {
            PropertyUser::query()
                ->where('property_id', $property->id)
                ->where('user_id', $staff->id)
                ->get()
                ->each(fn (PropertyUser $assignment) => $assignment->delete());
        });
    }
}
