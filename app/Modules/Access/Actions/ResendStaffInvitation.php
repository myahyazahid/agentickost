<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Models\StaffInvitation;
use App\Modules\Access\Models\User;
use App\Modules\Access\Support\InvitationMailer;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Sends an invitation again with a new link and a new expiry. The old link
 * stops working.
 */
final class ResendStaffInvitation extends Action
{
    public function __construct(private readonly InvitationMailer $mailer) {}

    public function handle(StaffInvitation $invitation): StaffInvitation
    {
        $this->authorize('create', User::class);

        if (! $invitation->isPending() && ! $invitation->isExpired()) {
            throw ValidationException::withMessages(['email' => 'Undangan ini sudah diterima atau dibatalkan.']);
        }

        return $this->transaction(function () use ($invitation): StaffInvitation {
            $this->mailer->send($invitation);

            return $invitation;
        });
    }
}
