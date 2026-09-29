<?php

namespace App\Modules\Property\Listeners;

use App\Modules\Access\Events\StaffInvitationAccepted;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Models\Property;

/**
 * Gives a new staff member the properties chosen when they were invited
 * (FR-USR-02, FR-USR-03). Properties deleted since then are skipped.
 */
final class AssignInvitedStaff
{
    public function __construct(private readonly AssignStaffToProperty $assign) {}

    public function handle(StaffInvitationAccepted $event): void
    {
        $properties = Property::query()->whereKey($event->invitation->property_ids)->get();

        foreach ($properties as $property) {
            $this->assign->handle($property, $event->user);
        }
    }
}
