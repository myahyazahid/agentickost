<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Events\StaffInvitationAccepted;
use App\Modules\Access\Models\StaffInvitation;
use App\Modules\Access\Models\User;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns an invitation into a staff account (FR-USR-03). Opening the emailed
 * link proves the address, so the account starts verified. The public
 * invitation page runs this as the system actor; the token is the gate.
 */
final class AcceptStaffInvitation extends Action
{
    public function __construct(
        private readonly CreateUser $createUser,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  array<string, mixed>  $input  password, password_confirmation
     */
    public function handle(string $token, array $input): User
    {
        $invitation = StaffInvitation::findByToken($token);

        if ($invitation === null || ! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'password' => 'Tautan undangan ini sudah tidak berlaku. Minta pengirimnya mengirim ulang undangan.',
            ]);
        }

        return $this->tenants->run($invitation->tenant()->firstOrFail(), function () use ($invitation, $input): User {
            $this->authorize('create', User::class);

            $data = $this->validate($input, [
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            if (DB::table('users')->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages([
                    'password' => "{$invitation->email} sudah punya akun KostPilot. Masuk dengan akun itu, atau minta undangan ke email lain.",
                ]);
            }

            return $this->transaction(function () use ($invitation, $data): User {
                $user = $this->createUser->handle([
                    'name' => $invitation->name,
                    'email' => $invitation->email,
                    'password' => $data['password'],
                    'role' => $invitation->role->value,
                ]);

                $invitation->accepted_at = now();
                $invitation->user_id = $user->id;
                $invitation->save();

                StaffInvitationAccepted::dispatch($invitation, $user);

                return $user;
            });
        });
    }
}
