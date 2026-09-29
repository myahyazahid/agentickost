<?php

namespace App\Modules\Access\Actions;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\StaffInvitation;
use App\Modules\Access\Models\User;
use App\Modules\Access\Support\InvitationMailer;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use App\Support\Subscriptions\SubscriptionGate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Invites a staff member by email (FR-USR-03), with a role and, for staff
 * who only see some properties, the properties to assign on acceptance.
 */
final class InviteStaff extends Action
{
    public function __construct(
        private readonly InvitationMailer $mailer,
        private readonly TenantContext $tenants,
        private readonly ActorContext $actors,
        private readonly SubscriptionGate $subscription,
    ) {}

    /**
     * @param  array<string, mixed>  $input  name, email, role, property_ids
     */
    public function handle(array $input): StaffInvitation
    {
        $this->authorize('create', User::class);

        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }

        $data = $this->validate($input, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(Role::class)->only(Role::staff())],
            'property_ids' => ['nullable', 'array'],
            'property_ids.*' => ['string', 'distinct', Rule::exists('properties', 'id')->where('tenant_id', $this->tenants->id())->whereNull('deleted_at')],
        ]);

        if (StaffInvitation::query()->open()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => "{$data['email']} sudah diundang. Kirim ulang undangannya dari daftar undangan.",
            ]);
        }

        $this->subscription->ensureCanAdd(SubscriptionGate::STAFF, errorKey: 'email');

        $actor = $this->actors->current();

        return $this->transaction(function () use ($data, $actor): StaffInvitation {
            $invitation = new StaffInvitation([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'property_ids' => array_values($data['property_ids'] ?? []),
                'invited_by' => $actor->type === ActorType::User ? $actor->id : null,
            ]);

            $this->mailer->send($invitation);

            return $invitation;
        });
    }
}
