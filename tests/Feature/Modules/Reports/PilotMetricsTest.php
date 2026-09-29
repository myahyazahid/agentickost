<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Payment\Actions\VerifyPayment;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Reports\Filament\Admin\Pages\PilotMetricsPage;
use App\Modules\Reports\Support\PilotMetrics;
use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\PaymentScenario;

/*
 * September: contract A's first invoice (due 1 Sep) is paid on 1 Sep, on
 * time; contract B's (due 5 Sep) is paid on 12 Sep, late. B's payment is
 * recorded by a caretaker and verified three hours later.
 */
beforeEach(function () {
    $this->travelTo('2026-09-01 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->contractA = LeaseScenario::active(overrides: ['start_date' => '2026-09-01']);
    BillingScenario::issueDue($this->contractA);
    PaymentScenario::transfer($this->contractA, 2_400_000);

    $this->travelTo('2026-09-05 03:00:00');
    $this->contractB = LeaseScenario::active(overrides: ['start_date' => '2026-09-05']);
    BillingScenario::issueDue($this->contractB);

    $this->travelTo('2026-09-12 02:00:00');
    $caretaker = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->contractB->property()->firstOrFail(), $caretaker);
    loginAs($caretaker);
    $payment = PaymentScenario::transfer($this->contractB, 2_400_000);

    $this->travelTo('2026-09-12 05:00:00');
    loginAs($this->owner);
    app(VerifyPayment::class)->handle($payment);

    $this->travelTo('2026-10-02 03:00:00');
});

function september(): array
{
    $from = CarbonImmutable::parse('2026-09-01', 'Asia/Jakarta');

    return [$from, $from->endOfMonth()];
}

it('counts rent invoices paid by their due date', function () {
    expect(PilotMetrics::onTimePayment(...september()))->toBe(['due' => 2, 'on_time' => 1, 'percent' => 50]);
});

it('takes the median wait of payments that went through the verification queue', function () {
    expect(PilotMetrics::verificationTime(...september()))->toBe(['verified' => 1, 'median_seconds' => 3 * 3600]);
});

it('shows each tenant\'s metrics to the super admin', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($admin, 'platform');
    actors()->set(Actor::platformAdmin($admin));
    tenancy()->forget();

    Livewire::test(PilotMetricsPage::class)
        ->fillForm(['month' => '2026-09'])
        ->assertSee([$this->owner->tenant()->value('name'), '50%', '1 dari 2 tagihan', '3 jam', '1 pembayaran lewat antrean']);
});
