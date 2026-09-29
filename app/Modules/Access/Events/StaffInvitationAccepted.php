<?php

namespace App\Modules\Access\Events;

use App\Modules\Access\Models\StaffInvitation;
use App\Modules\Access\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A staff member accepted an invitation and has an account. The Property
 * module assigns the properties chosen in the invitation.
 */
final class StaffInvitationAccepted
{
    use Dispatchable;

    public function __construct(
        public readonly StaffInvitation $invitation,
        public readonly User $user,
    ) {}
}
