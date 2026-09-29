<?php

use App\Modules\Access\Actions\InviteStaff;
use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Property\Actions\CreateRoom;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomType;
use App\Modules\Subscription\Actions\AdvanceSubscription;
use App\Modules\Subscription\Actions\CancelSubscription;
use App\Modules\Subscription\Actions\ChoosePlan;
use App\Modules\Subscription\Actions\MarkSubscriptionInvoicePaid;
use App\Modules\Subscription\Actions\ResumeSubscription;
use App\Modules\Subscription\Actions\VoidSubscriptionInvoice;
use App\Modules\Subscription\Enums\SubscriptionEvent;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionInvoice;
use App\Modules\Subscription\States\Subscription\Active;
use App\Modules\Subscription\States\Subscription\Cancelled;
use App\Modules\Subscription\States\Subscription\Frozen;
use App\Modules\Subscription\States\Subscription\Grace;
use App\Modules\Subscription\States\Subscription\Restricted;
use App\Modules\Subscription\States\Subscription\Trial;
use App\Modules\Subscription\States\SubscriptionInvoice\Paid;
use App\Modules\Subscription\States\SubscriptionInvoice\Unpaid;
use App\Modules\Subscription\States\SubscriptionInvoice\Voided;
use App\Modules\Subscription\Support\CurrentSubscription;
use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Actors\Actor;
use App\Support\Subscriptions\ReadOnlyMode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    $this->travelTo('2026-10-05 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->tenant = tenancy()->tenant();
    // End of 19 October in WIB, as registration stores it.
    $this->tenant->update(['trial_ends_at' => '2026-10-19 16:59:59']);
    $this->plan = Plan::factory()->create(['name' => 'Dasar', 'monthly_price_amount' => 150_000, 'max_rooms' => 20]);
});

function choosePlan(Plan $plan, string $cycle = 'monthly'): ?SubscriptionInvoice
{
    return app(ChoosePlan::class)->handle(['plan_id' => $plan->id, 'billing_cycle' => $cycle]);
}

/**
 * Confirms the payment as a super admin, outside the tenant, the way the
 * admin panel does.
 */
function confirmPayment(SubscriptionInvoice $invoice, string $paidAt = 'now'): void
{
    $tenant = tenancy()->tenant();
    $actor = actors()->current();
    tenancy()->forget();

    actors()->actingAs(
        Actor::platformAdmin(PlatformAdmin::factory()->create()),
        fn () => app(MarkSubscriptionInvoicePaid::class)->handle($invoice, ['paid_at' => now()->parse($paidAt)->toDateTimeString(), 'payment_note' => 'Transfer BCA']),
    );

    tenancy()->set($tenant);
    actors()->set($actor);
}

/**
 * @return list<SubscriptionEvent>
 */
function advanceSubscription(): array
{
    return actors()->actingAs(Actor::system(), fn () => app(AdvanceSubscription::class)->handle());
}

function subscription(): Subscription
{
    return CurrentSubscription::get()->refresh();
}

it('starts every tenant on a trial without a plan', function () {
    expect(subscription()->status)->toBeInstanceOf(Trial::class)
        ->and(subscription()->plan_id)->toBeNull();
});

it('bills the first period after the trial, due on its last day', function () {
    $invoice = choosePlan($this->plan);

    expect($invoice)->not->toBeNull()
        ->and($invoice->number)->toBe('AK/LGN/2026/10/00001')
        ->and($invoice->amount)->toBe(150_000)
        ->and($invoice->period_start->toDateString())->toBe('2026-10-20')
        ->and($invoice->period_end->toDateString())->toBe('2026-11-19')
        ->and($invoice->due_date->toDateString())->toBe('2026-10-19')
        ->and(subscription()->status)->toBeInstanceOf(Trial::class)
        ->and(subscription()->plan_id)->toBe($this->plan->id);
});

it('replaces the unpaid invoice when the owner picks another plan', function () {
    $first = choosePlan($this->plan);
    $second = choosePlan($this->plan, 'yearly');

    expect($first->refresh()->status)->toBeInstanceOf(Voided::class)
        ->and($second->amount)->toBe(1_500_000)
        ->and($second->period_end->toDateString())->toBe('2027-10-19')
        ->and($second->number)->toBe('AK/LGN/2026/10/00002');
});

it('refuses a cycle the plan is not sold in', function () {
    $monthlyOnly = Plan::factory()->create(['yearly_price_amount' => null]);

    expect(fn () => choosePlan($monthlyOnly, 'yearly'))->toThrow(ValidationException::class, 'tidak dijual tahunan');
});

it('refuses a plan whose limits the tenant already exceeds', function () {
    $small = Plan::factory()->create(['name' => 'Kecil', 'max_rooms' => 2]);
    Room::factory()->count(3)->forType(RoomType::factory()->create())->create();

    expect(fn () => choosePlan($small))->toThrow(ValidationException::class, '3 kamar, paket Kecil hanya 2');
});

it('activates the subscription for the paid period', function () {
    $invoice = choosePlan($this->plan);

    confirmPayment($invoice);

    expect($invoice->refresh()->status)->toBeInstanceOf(Paid::class)
        ->and($invoice->payment_note)->toBe('Transfer BCA')
        ->and($invoice->confirmed_by)->not->toBeNull()
        ->and(subscription()->status)->toBeInstanceOf(Active::class)
        ->and(subscription()->current_period_start?->toDateString())->toBe('2026-10-20')
        ->and(subscription()->current_period_end?->toDateString())->toBe('2026-11-19');
});

it('does not take a payment twice', function () {
    $invoice = choosePlan($this->plan);
    confirmPayment($invoice);

    expect(fn () => confirmPayment($invoice))->toThrow(ValidationException::class, 'sudah lunas atau dibatalkan');
});

it('lets a super admin void an unpaid invoice', function () {
    $invoice = choosePlan($this->plan);
    tenancy()->forget();

    actors()->actingAs(Actor::platformAdmin(PlatformAdmin::factory()->create()), fn () => app(VoidSubscriptionInvoice::class)->handle($invoice));

    expect(tenancy()->run($this->tenant, fn () => $invoice->refresh()->status))->toBeInstanceOf(Voided::class);
});

it('switches plans at once while a paid period runs', function () {
    confirmPayment(choosePlan($this->plan));
    $bigger = Plan::factory()->create(['monthly_price_amount' => 300_000]);

    expect(choosePlan($bigger))->toBeNull()
        ->and(subscription()->plan_id)->toBe($bigger->id)
        ->and(subscription()->status)->toBeInstanceOf(Active::class);
});

it('enforces the plan limits on rooms and staff', function () {
    $small = Plan::factory()->create(['name' => 'Kecil', 'max_rooms' => 2, 'max_staff' => 1]);
    confirmPayment(choosePlan($small));
    $roomType = RoomType::factory()->create();
    Room::factory()->count(2)->forType($roomType)->create();

    expect(fn () => app(CreateRoom::class)->handle($roomType->property()->firstOrFail(), ['room_type_id' => $roomType->id, 'number' => 'A1']))
        ->toThrow(ValidationException::class, 'Paket Kecil dibatasi 2 kamar');

    Mail::fake();

    try {
        app(InviteStaff::class)->handle(['name' => 'Rina', 'email' => 'rina@example.com', 'role' => Role::Manager->value]);
        $this->fail('The invitation should be refused.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('email')
            ->and($exception->errors()['email'][0])->toContain('dibatasi 1 pengguna');
    }
});

it('puts a trial that ended unpaid in grace, then read-only, then frozen', function () {
    Room::factory()->forType($roomType = RoomType::factory()->create())->create();

    // 00:30 WIB on 20 October, the first day after the trial.
    $this->travelTo('2026-10-19 17:30:00');
    expect(advanceSubscription())->toBe([SubscriptionEvent::GraceStarted])
        ->and(subscription()->status)->toBeInstanceOf(Grace::class)
        ->and(subscription()->grace_ends_at?->toDateTimeString())->toBe('2026-10-26 17:00:00');

    app(CreateRoom::class)->handle($roomType->property()->firstOrFail(), ['room_type_id' => $roomType->id, 'number' => 'G1']);

    $this->travelTo('2026-10-26 17:00:00');
    expect(advanceSubscription())->toBe([SubscriptionEvent::ReadOnlyStarted])
        ->and(subscription()->status)->toBeInstanceOf(Restricted::class)
        ->and(fn () => app(CreateRoom::class)->handle($roomType->property()->firstOrFail(), ['room_type_id' => $roomType->id, 'number' => 'G2']))
        ->toThrow(ReadOnlyMode::class);

    $this->travelTo('2026-11-25 16:59:00');
    expect(advanceSubscription())->toBe([]);

    $this->travelTo('2026-11-25 17:00:00');
    expect(advanceSubscription())->toBe([SubscriptionEvent::Frozen])
        ->and(subscription()->status)->toBeInstanceOf(Frozen::class)
        ->and($this->tenant->refresh()->isFrozen())->toBeTrue()
        ->and($this->tenant->frozen_reason)->toContain('Langganan tidak dibayar');
});

it('catches up several stages in one run', function () {
    $this->travelTo('2026-10-28 03:00:00');

    expect(advanceSubscription())->toBe([SubscriptionEvent::GraceStarted, SubscriptionEvent::ReadOnlyStarted])
        ->and(subscription()->status)->toBeInstanceOf(Restricted::class);
});

it('lets a read-only owner pick a plan, and paying opens a frozen tenant again', function () {
    $this->travelTo('2026-10-28 03:00:00');
    advanceSubscription();
    $this->travelTo('2026-12-01 03:00:00');
    advanceSubscription();
    expect($this->tenant->refresh()->isFrozen())->toBeTrue();

    $invoice = choosePlan($this->plan);
    expect($invoice->period_start->toDateString())->toBe('2026-12-01')
        ->and($invoice->due_date->toDateString())->toBe('2026-12-01');

    confirmPayment($invoice);

    expect(subscription()->status)->toBeInstanceOf(Active::class)
        ->and($this->tenant->refresh()->isFrozen())->toBeFalse()
        ->and(subscription()->current_period_end?->toDateString())->toBe('2026-12-31');
});

it('issues one renewal invoice a week before the period ends', function () {
    confirmPayment(choosePlan($this->plan));

    $this->travelTo('2026-11-11 03:00:00');
    expect(advanceSubscription())->toBe([]);

    $this->travelTo('2026-11-12 03:00:00');
    expect(advanceSubscription())->toBe([SubscriptionEvent::RenewalIssued])
        ->and(advanceSubscription())->toBe([]);

    $renewal = SubscriptionInvoice::query()->where('status', Unpaid::$name)->sole();

    expect($renewal->period_start->toDateString())->toBe('2026-11-20')
        ->and($renewal->period_end->toDateString())->toBe('2026-12-19')
        ->and($renewal->due_date->toDateString())->toBe('2026-11-19')
        ->and($renewal->number)->toBe('AK/LGN/2026/11/00001');

    // Unpaid past the period: grace. Paying it runs the subscription on.
    $this->travelTo('2026-11-20 03:00:00');
    expect(advanceSubscription())->toBe([SubscriptionEvent::GraceStarted]);

    confirmPayment($renewal);

    expect(subscription()->status)->toBeInstanceOf(Active::class)
        ->and(subscription()->grace_ends_at)->toBeNull()
        ->and(subscription()->current_period_end?->toDateString())->toBe('2026-12-19');
});

it('runs a cancelled subscription to the end of its period, then makes it read-only', function () {
    confirmPayment(choosePlan($this->plan));

    $this->travelTo('2026-11-12 03:00:00');
    advanceSubscription();
    app(CancelSubscription::class)->handle();

    expect(subscription()->status)->toBeInstanceOf(Cancelled::class)
        ->and(SubscriptionInvoice::query()->where('status', Unpaid::$name)->exists())->toBeFalse();

    $this->travelTo('2026-11-19 16:00:00');
    expect(advanceSubscription())->toBe([]);

    $this->travelTo('2026-11-19 17:00:00');
    expect(advanceSubscription())->toBe([SubscriptionEvent::ReadOnlyStarted]);
});

it('takes back a cancellation while the period runs', function () {
    confirmPayment(choosePlan($this->plan));
    app(CancelSubscription::class)->handle();

    app(ResumeSubscription::class)->handle();

    expect(subscription()->status)->toBeInstanceOf(Active::class)
        ->and(subscription()->cancelled_at)->toBeNull();
});

it('only lets the owner manage the subscription', function () {
    loginAs(staff(Role::Manager, $this->tenant));

    choosePlan($this->plan);
})->throws(AuthorizationException::class);

it('skips read-only tenants in scheduled billing without reporting a failure', function () {
    $this->travelTo('2026-10-28 03:00:00');
    LeaseScenario::active(overrides: ['start_date' => '2026-11-15']);
    advanceSubscription();
    $invoices = Invoice::query()->count();
    tenancy()->forget();
    auth()->logout();

    $this->travelTo('2026-11-15 03:00:00');
    $this->artisan('billing:issue-invoices')->expectsOutputToContain('0 tagihan diterbitkan.')->assertSuccessful();

    expect(tenancy()->run($this->tenant, fn () => Invoice::query()->count()))->toBe($invoices);
});

it('advances every tenant and tells the owners', function () {
    $other = Tenant::factory()->create(['trial_ends_at' => '2026-12-31 16:59:59']);
    $this->travelTo('2026-10-20 03:00:00');

    $this->artisan('subscriptions:advance')->assertSuccessful()->expectsOutputToContain('1 langganan berubah');

    expect(subscription()->status)->toBeInstanceOf(Grace::class)
        ->and(tenancy()->run($other, fn () => CurrentSubscription::get()->status))->toBeInstanceOf(Trial::class)
        ->and(DatabaseNotification::query()->where('notifiable_id', $this->owner->id)->where('data->title', 'Langganan masuk masa tenggang')->exists())->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});
