<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Property\Filament\App\Resources\Properties\Pages\CreateProperty;
use App\Modules\Property\Models\Property;
use App\Modules\Subscription\Actions\AdvanceSubscription;
use App\Modules\Subscription\Actions\ChoosePlan;
use App\Modules\Subscription\Actions\MarkSubscriptionInvoicePaid;
use App\Modules\Subscription\Filament\Admin\Pages\SubscriptionSettingsPage;
use App\Modules\Subscription\Filament\Admin\Resources\Plans\Pages\CreatePlan;
use App\Modules\Subscription\Filament\Admin\Resources\SubscriptionInvoices\Pages\ListSubscriptionInvoices;
use App\Modules\Subscription\Filament\Admin\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Modules\Subscription\Filament\App\Pages\SubscriptionPage;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\SubscriptionInvoice;
use App\Modules\Subscription\States\Subscription\Active;
use App\Modules\Subscription\Support\BillingSettings;
use App\Modules\Subscription\Support\CurrentSubscription;
use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Modules\Tenancy\Support\PlatformSettings;
use App\Support\Actors\Actor;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-10-05 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->tenant = tenancy()->tenant();
    $this->tenant->update(['trial_ends_at' => '2026-10-19 16:59:59']);
    $this->plan = Plan::factory()->create(['name' => 'Dasar', 'monthly_price_amount' => 150_000, 'max_rooms' => 20, 'max_properties' => 1]);
    PlatformSettings::put(BillingSettings::PAYMENT_INSTRUCTIONS, 'Transfer ke BCA 1234567890 a.n. PT Agentic Kost.');
});

/**
 * Picks the plan and has a super admin confirm its first invoice.
 */
function confirmPlanPaid(Plan $plan): void
{
    $invoice = app(ChoosePlan::class)->handle(['plan_id' => $plan->id, 'billing_cycle' => 'monthly']);
    $tenant = tenancy()->tenant();
    $actor = actors()->current();
    tenancy()->forget();

    actors()->actingAs(
        Actor::platformAdmin(PlatformAdmin::factory()->create()),
        fn () => app(MarkSubscriptionInvoicePaid::class)->handle($invoice, ['paid_at' => now()->toDateTimeString()]),
    );

    tenancy()->set($tenant);
    actors()->set($actor);
}

function asSuperAdmin(): PlatformAdmin
{
    $admin = PlatformAdmin::factory()->create();
    tenancy()->forget();
    auth()->logout();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    test()->actingAs($admin, 'platform');
    actors()->set(Actor::platformAdmin($admin));

    return $admin;
}

it('shows the owner the trial, usage, and plans', function () {
    $this->get('/app/langganan')
        ->assertOk()
        ->assertSee(['Trial', 'Belum memilih paket', 'Trial sampai', '19 Oktober 2026', 'Pilih paket']);

    $this->get('/app')->assertOk()->assertSee(['Masa trial: sisa 15 hari', 'Pilih paket di menu Langganan']);
});

it('lets the owner choose a plan and shows how to pay', function () {
    Livewire::test(SubscriptionPage::class)
        ->callAction('choosePlan', ['plan_id' => $this->plan->id, 'billing_cycle' => 'monthly'])
        ->assertHasNoActionErrors()
        ->assertNotified('Paket disimpan');

    $this->get('/app/langganan')
        ->assertOk()
        ->assertSee(['AK/LGN/2026/10/00001', 'Cara bayar', 'Transfer ke BCA 1234567890', 'Dasar, Bulanan']);
});

it('keeps the subscription page from staff other than the owner', function () {
    loginAs(staff(Role::Manager, $this->tenant));

    $this->get('/app/langganan')->assertForbidden();
});

it('shows every user a read-only banner and refuses to save', function () {
    $this->travelTo('2026-10-28 03:00:00');
    actors()->actingAs(Actor::system(), fn () => app(AdvanceSubscription::class)->handle());

    loginAs(staff(Role::Manager, $this->tenant));
    $this->get('/app')->assertOk()->assertSee(['Mode baca saja.', 'Buka Langganan']);

    loginAs($this->owner);
    Livewire::test(CreateProperty::class)
        ->fillForm([
            'name' => 'Kost Baru',
            'code' => 'KB',
            'address' => 'Jl. Mawar 1',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'timezone' => 'Asia/Jakarta',
            'gender_policy' => 'mixed',
        ])
        ->call('create')
        ->assertNotified('Mode baca saja');
});

it('shows a plan limit on the form field', function () {
    Property::factory()->create();
    confirmPlanPaid($this->plan);

    Livewire::test(CreateProperty::class)
        ->fillForm([
            'name' => 'Kost Kedua',
            'code' => 'K2',
            'address' => 'Jl. Melati 2',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'timezone' => 'Asia/Jakarta',
            'gender_policy' => 'mixed',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'Paket Dasar dibatasi 1 properti. Naikkan paket di menu Langganan untuk menambah lagi.']);
});

it('lets a super admin create plans and confirm a transfer', function () {
    $invoice = app(ChoosePlan::class)->handle(['plan_id' => $this->plan->id, 'billing_cycle' => 'monthly']);
    asSuperAdmin();

    Livewire::test(CreatePlan::class)
        ->fillForm([
            'name' => 'Pro',
            'code' => 'pro',
            'monthly_price_amount' => '300.000',
            'max_rooms' => 100,
            'features' => ['advanced_reports'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Plan::query()->where('code', 'pro')->sole())
        ->monthly_price_amount->toBe(300_000)
        ->features->toBe(['advanced_reports']);

    Livewire::test(ListSubscriptionInvoices::class)
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee($this->tenant->name)
        ->callAction(TestAction::make('markPaid')->table($invoice), ['paid_at' => now()->toDateTimeString(), 'payment_note' => 'BCA a.n. Budi'])
        ->assertHasNoActionErrors()
        ->assertNotified('AK/LGN/2026/10/00001 lunas');

    expect(tenancy()->run($this->tenant, fn () => CurrentSubscription::get()->status))->toBeInstanceOf(Active::class);

    Livewire::test(ListSubscriptions::class)->assertSee([$this->tenant->name, 'Aktif', 'Dasar']);
});

it('lets a super admin set the grace and read-only periods', function () {
    asSuperAdmin();

    Livewire::test(SubscriptionSettingsPage::class)
        ->assertSchemaStateSet(['grace_days' => 7, 'read_only_days' => 30])
        ->fillForm(['grace_days' => 3, 'read_only_days' => 60, 'payment_instructions' => 'Transfer ke Mandiri.'])
        ->call('save')
        ->assertNotified('Pengaturan tersimpan');

    expect(BillingSettings::graceDays())->toBe(3)
        ->and(BillingSettings::readOnlyDays())->toBe(60)
        ->and(BillingSettings::paymentInstructions())->toBe('Transfer ke Mandiri.');
});

it('keeps subscription invoices of every tenant from owners', function () {
    expect(SubscriptionInvoice::query()->count())->toBe(0);

    $this->get('/admin/tagihan-langganan')->assertRedirect();
});
