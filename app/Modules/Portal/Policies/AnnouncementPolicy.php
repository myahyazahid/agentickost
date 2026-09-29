<?php

namespace App\Modules\Portal\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Portal\Enums\PortalPermission;
use App\Modules\Portal\Models\Announcement;
use App\Modules\Property\Models\Property;

/**
 * Owners and managers write announcements for the properties they run
 * (FR-PRT-05).
 */
final class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PortalPermission::ManageAnnouncements->value);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function createIn(User $user, Property $property): bool
    {
        return $this->viewAny($user) && $property->isAccessibleBy($user);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $this->viewAny($user) && $announcement->property()->firstOrFail()->isAccessibleBy($user);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement);
    }
}
