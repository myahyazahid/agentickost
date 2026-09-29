<?php

use App\Modules\Access\Actions\AcceptStaffInvitation;
use App\Modules\Access\Actions\CancelStaffInvitation;
use App\Modules\Access\Actions\InviteStaff;
use App\Modules\Access\Actions\ResendStaffInvitation;
use App\Modules\Access\Enums\Role;
use App\Modules\Access\Filament\App\Auth\AcceptInvitation;
use App\Modules\Access\Filament\App\Resources\Users\Pages\ListUsers;
use App\Modules\Access\Filament\App\Resources\Users\Widgets\PendingInvitations;
use App\Modules\Access\Models\StaffInvitation;
use App\Modules\Access\Models\User;
use App\Modules\Access\Notifications\StaffInvitationNotification;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Scopes\TenantScope;
use App\Support\Actors\Actor;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Notification::fake();
    $this->travelTo('2026-10-05 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->property = Property::factory()->create(['name' => 'Kost Melati']);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function inviteRina(array $overrides = []): StaffInvitation
{
    return app(InviteStaff::class)->handle([
        'name' => 'Rina Wulandari',
        'email' => 'Rina@Example.com',
        'role' => Role::Caretaker->value,
        'property_ids' => [test()->property->id],
        ...$overrides,
    ]);
}

/**
 * The token in the last invitation email sent to an address.
 */
function invitationToken(string $email): string
{
    $notification = Notification::sent(new AnonymousNotifiable, StaffInvitationNotification::class)
        ->filter(fn (StaffInvitationNotification $notification): bool => $notification->invitation->email === $email)
        ->last();

    return (string) str($notification->url)->afterLast('/');
}

function acceptAs(string $token, string $password = 'rahasia-staf-123'): User
{
    return actors()->actingAs(Actor::system(), fn () => app(AcceptStaffInvitation::class)->handle($token, [
        'password' => $password,
        'password_confirmation' => $password,
    ]));
}

it('emails an invitation with a link that makes a verified staff account with its properties', function () {
    $invitation = inviteRina();

    Notification::assertSentOnDemand(StaffInvitationNotification::class, fn (StaffInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === ['rina@example.com' => 'Rina Wulandari']
        && str_contains($notification->toMail($notifiable)->subject, $this->owner->tenant()->value('name')));

    tenancy()->forget();
    $user = acceptAs(invitationToken('rina@example.com'));

    expect($user->email)->toBe('rina@example.com')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(tenancy()->run($user->tenant()->firstOrFail(), fn () => $user->hasRole(Role::Caretaker->value)))->toBeTrue()
        ->and(tenancy()->run($user->tenant()->firstOrFail(), fn () => $this->property->staff()->whereKey($user->id)->exists()))->toBeTrue()
        ->and(StaffInvitation::query()->withoutGlobalScope(TenantScope::class)->find($invitation->id)?->accepted_at)->not->toBeNull();
});

it('accepts through the invitation page and logs the staff member in', function () {
    inviteRina();
    $token = invitationToken('rina@example.com');
    auth()->logout();
    tenancy()->forget();
    actors()->forget();

    $this->get("/app/undangan/{$token}")->assertOk()->assertSee(['Bergabung dengan', 'Anda diundang sebagai Penjaga']);

    Livewire::test(AcceptInvitation::class, ['token' => $token])
        ->fillForm(['password' => 'rahasia-staf-123', 'password_confirmation' => 'rahasia-staf-123'])
        ->call('accept')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getUrl());

    $this->assertAuthenticatedAs(User::query()->withoutGlobalScope(TenantScope::class)->where('email', 'rina@example.com')->sole());
});

it('stops old links when an invitation is sent again, cancelled, or expired', function () {
    $invitation = inviteRina();
    $first = invitationToken('rina@example.com');

    app(ResendStaffInvitation::class)->handle($invitation);
    $second = invitationToken('rina@example.com');

    expect($second)->not->toBe($first)
        ->and(fn () => acceptAs($first))->toThrow(ValidationException::class, 'sudah tidak berlaku');

    $this->travel(StaffInvitation::VALID_DAYS + 1)->days();
    expect(fn () => acceptAs($second))->toThrow(ValidationException::class, 'sudah tidak berlaku');

    app(ResendStaffInvitation::class)->handle($invitation->refresh());
    app(CancelStaffInvitation::class)->handle($invitation->refresh());
    expect(fn () => acceptAs(invitationToken('rina@example.com')))->toThrow(ValidationException::class, 'sudah tidak berlaku');

    auth()->logout();
    $this->get('/app/undangan/'.invitationToken('rina@example.com'))->assertOk()->assertSee('Undangan tidak berlaku');
});

it('refuses to invite an address that has an account or an open invitation', function () {
    staff(Role::Manager)->update(['email' => 'budi@example.com']);
    inviteRina();

    expect(fn () => inviteRina(['email' => 'budi@example.com']))->toThrow(ValidationException::class)
        ->and(fn () => inviteRina())->toThrow(ValidationException::class, 'sudah diundang');
});

it('lets only the owner invite staff', function () {
    loginAs(staff(Role::Manager, $this->owner->tenant()->firstOrFail()));

    expect(fn () => inviteRina())->toThrow(AuthorizationException::class);
});

it('invites from the staff page and lists the invitation until it is accepted', function () {
    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->owner])
        ->callAction('invite', [
            'name' => 'Rina Wulandari',
            'email' => 'rina@example.com',
            'role' => Role::Manager->value,
            'property_ids' => [$this->property->id],
        ])
        ->assertNotified('Undangan dikirim ke rina@example.com');

    $invitation = StaffInvitation::query()->sole();

    Livewire::test(PendingInvitations::class)
        ->assertCanSeeTableRecords([$invitation])
        ->callAction(TestAction::make('cancel')->table($invitation))
        ->assertNotified('Undangan dibatalkan')
        ->assertCanNotSeeTableRecords([$invitation]);
});
