<?php

namespace App\Modules\Maintenance\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\Support\PortalAccess;
use App\Modules\Maintenance\Enums\MaintenancePermission;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Property\Models\Property;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Staff report tickets for their properties, and residents report them from
 * the portal for the place they live (FR-PRT-04). Owners and managers
 * assign, reject, and confirm them; the assigned staff member does the work.
 */
final class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(MaintenancePermission::ViewTickets->value);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->viewAny($user) && $ticket->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return $user->can(MaintenancePermission::ReportTickets->value);
    }

    public function reportIn(User $user, Property $property): bool
    {
        return $user->can(MaintenancePermission::ReportTickets->value) && $property->isAccessibleBy($user);
    }

    public function reportFromPortal(Authenticatable $account, Contract $contract): bool
    {
        return $account instanceof Resident && PortalAccess::for($account)->livesIn($contract);
    }

    public function manage(User $user, Ticket $ticket): bool
    {
        return $user->can(MaintenancePermission::ManageTickets->value) && $ticket->isAccessibleBy($user);
    }

    public function work(User $user, Ticket $ticket): bool
    {
        return $this->manage($user, $ticket)
            || ($ticket->assigned_user_id === $user->id && $ticket->isAccessibleBy($user));
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
