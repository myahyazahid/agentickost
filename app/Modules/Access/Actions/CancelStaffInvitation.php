<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Models\StaffInvitation;
use App\Modules\Access\Models\User;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Withdraws an invitation; its link stops working.
 */
final class CancelStaffInvitation extends Action
{
    public function handle(StaffInvitation $invitation): StaffInvitation
    {
        $this->authorize('create', User::class);

        if ($invitation->accepted_at !== null || $invitation->cancelled_at !== null) {
            throw ValidationException::withMessages(['email' => 'Undangan ini sudah diterima atau dibatalkan.']);
        }

        return $this->transaction(function () use ($invitation): StaffInvitation {
            $invitation->cancelled_at = now();
            $invitation->save();

            return $invitation;
        });
    }
}
