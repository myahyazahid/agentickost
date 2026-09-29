<?php

namespace App\Modules\Access\Support;

use App\Modules\Access\Models\StaffInvitation;
use App\Modules\Access\Notifications\StaffInvitationNotification;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Gives an invitation a fresh link and emails it. Each send replaces the
 * token, so an older link stops working.
 */
final class InvitationMailer
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function send(StaffInvitation $invitation): void
    {
        $token = Str::random(40);

        $invitation->token_hash = StaffInvitation::hashToken($token);
        $invitation->expires_at = now()->addDays(StaffInvitation::VALID_DAYS);
        $invitation->save();

        $tenant = $this->tenants->tenant();

        Notification::route('mail', [$invitation->email => $invitation->name])->notify(new StaffInvitationNotification(
            $invitation,
            $tenant->name,
            route('filament.app.invitation.accept', ['token' => $token]),
            $tenant->default_timezone,
        ));
    }
}
